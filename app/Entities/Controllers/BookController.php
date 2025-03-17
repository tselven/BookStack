<?php

namespace BookStack\Entities\Controllers;

use BookStack\Activity\ActivityQueries;
use BookStack\Entities\Models\Page;
use BookStack\Uploads\FileStorage;
use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\Log;
use BookStack\Activity\ActivityType;
use BookStack\Activity\Models\View;
use BookStack\Activity\Tools\UserEntityWatchOptions;
use BookStack\Entities\Queries\BookQueries;
use BookStack\Entities\Queries\BookshelfQueries;
use BookStack\Entities\Repos\BookRepo;
use BookStack\Entities\Tools\BookContents;
use BookStack\Entities\Tools\Cloner;
use BookStack\Entities\Tools\HierarchyTransformer;
use BookStack\Entities\Tools\ShelfContext;
use BookStack\Exceptions\ImageUploadException;
use BookStack\Exceptions\NotFoundException;
use BookStack\Facades\Activity;
use BookStack\Http\Controller;
use BookStack\References\ReferenceFetcher;
use BookStack\Util\SimpleListOptions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class BookController extends Controller
{
    protected FileStorage $fileStorage;
    public function __construct(
        protected ShelfContext $shelfContext,
        protected BookRepo $bookRepo,
        protected BookQueries $queries,
        protected BookshelfQueries $shelfQueries,
        protected ReferenceFetcher $referenceFetcher,
        FileStorage $fileStorage
    ) {
        $this->fileStorage = $fileStorage;
    }

    public function extractPdfText()
    {
        $parser = new Parser();
        $pdf = $parser->parseFile(storage_path('app/public/sample.pdf')); // Path to your PDF file
        $text = $pdf->getText(); // Extracted text

        return response()->json(['content' => $text]);
    }

    /**
     * Display a listing of the book.
     */
    public function index(Request $request)
    {
        $view = setting()->getForCurrentUser('books_view_type');
        $listOptions = SimpleListOptions::fromRequest($request, 'books')->withSortOptions([
            'name' => trans('common.sort_name'),
            'created_at' => trans('common.sort_created_at'),
            'updated_at' => trans('common.sort_updated_at'),
        ]);

        $books = $this->queries->visibleForListWithCover()
            ->orderBy($listOptions->getSort(), $listOptions->getOrder())
            ->paginate(18);
        $recents = $this->isSignedIn() ? $this->queries->recentlyViewedForCurrentUser()->take(4)->get() : false;
        $popular = $this->queries->popularForList()->take(4)->get();
        $new = $this->queries->visibleForList()->orderBy('created_at', 'desc')->take(4)->get();

        $this->shelfContext->clearShelfContext();

        $this->setPageTitle(trans('entities.books'));

        return view('books.index', [
            'books'   => $books,
            'recents' => $recents,
            'popular' => $popular,
            'new'     => $new,
            'view'    => $view,
            'listOptions' => $listOptions,
        ]);
    }

    /**
     * Show the form for creating a new book.
     */
    public function create(?string $shelfSlug = null)
    {
        $this->checkPermission('book-create-all');

        $bookshelf = null;
        if ($shelfSlug !== null) {
            $bookshelf = $this->shelfQueries->findVisibleBySlugOrFail($shelfSlug);
            $this->checkOwnablePermission('bookshelf-update', $bookshelf);
        }

        $this->setPageTitle(trans('entities.books_create'));

        return view('books.create', [
            'bookshelf' => $bookshelf,
        ]);
    }

    /**
     * Store a newly created book in storage.
     *
     * @throws ImageUploadException
     * @throws ValidationException
     */
    public function store(Request $request, ?string $shelfSlug = null)
    {
        $this->checkPermission('book-create-all');

        Log::info('Store method started.');

        $validated = $this->validate($request, [
            'name'                => ['required', 'string', 'max:255'],
            'description_html'    => ['string', 'max:2000'],
            'image'               => array_merge(['nullable'], $this->getImageValidationRules()),
            'tags'                => ['array'],
            'book'                => ['nullable', 'file', 'mimes:pdf', 'max:51200'],
            'default_template_id' => ['nullable', 'integer'],
        ]);

        $bookshelf = null;
        if ($shelfSlug !== null) {
            $bookshelf = $this->shelfQueries->findVisibleBySlugOrFail($shelfSlug);
            $this->checkOwnablePermission('bookshelf-update', $bookshelf);
            Log::info("Bookshelf with slug {$shelfSlug} found.");
        }

        // Create book entry
        $book = $this->bookRepo->create($validated);
        Log::info("Book created with ID: {$book->id}");

        if ($bookshelf) {
            $bookshelf->appendBook($book);
            Activity::add(ActivityType::BOOKSHELF_UPDATE, $bookshelf);
            Log::info("Book added to bookshelf: {$bookshelf->id}");
        }

        // If a PDF file is uploaded, parse its text and save pages
        if ($request->hasFile('book')) {
            Log::info('PDF file uploaded, starting processing.');

            // Use the configured disk for PDF storage
            $disk = config('filesystems.default'); // Get default disk from config
            $pdfPath = $request->file('book')->store('pdfs', $disk); // Store PDF in 'pdfs' folder on the selected disk
            Log::info("PDF file stored at path: {$pdfPath}");

            $parser = new Parser();
            $pdf = $parser->parseFile(public_path($pdfPath));
            $pages = $pdf->getPages(); // Extract each page separately
            Log::info('PDF parsed, starting page processing.');

            foreach ($pages as $index => $page) {
                Page::create([
                    'name'      => "Page " . ($index + 1), // Assign a name to each page
                    'book_id'   => $book->id, // Link to the book
                    'html'      => nl2br(e($page->getText())), // Convert text to HTML format
                    'text'      => $page->getText(),
                    'draft'     => false,
                    'template'  => false,
                ]);
                Log::info("Page $index processed and saved.");
            }
        }

        Log::info('Store method finished. Redirecting to book URL.');

        return redirect($book->getUrl());
    }


    /**
     * Display the specified book.
     */
    public function show(Request $request, ActivityQueries $activities, string $slug)
    {
        $book = $this->queries->findVisibleBySlugOrFail($slug);
        $bookChildren = (new BookContents($book))->getTree(true);
        $bookParentShelves = $book->shelves()->scopes('visible')->get();

        View::incrementFor($book);
        if ($request->has('shelf')) {
            $this->shelfContext->setShelfContext(intval($request->get('shelf')));
        }

        $this->setPageTitle($book->getShortName());

        return view('books.show', [
            'book'              => $book,
            'current'           => $book,
            'bookChildren'      => $bookChildren,
            'bookParentShelves' => $bookParentShelves,
            'watchOptions'      => new UserEntityWatchOptions(user(), $book),
            'activity'          => $activities->entityActivity($book, 20, 1),
            'referenceCount'    => $this->referenceFetcher->getReferenceCountToEntity($book),
        ]);
    }

    /**
     * Show the form for editing the specified book.
     */
    public function edit(string $slug)
    {
        $book = $this->queries->findVisibleBySlugOrFail($slug);
        $this->checkOwnablePermission('book-update', $book);
        $this->setPageTitle(trans('entities.books_edit_named', ['bookName' => $book->getShortName()]));

        return view('books.edit', ['book' => $book, 'current' => $book]);
    }

    /**
     * Update the specified book in storage.
     *
     * @throws ImageUploadException
     * @throws ValidationException
     * @throws Throwable
     */
    public function update(Request $request, string $slug)
    {
        $book = $this->queries->findVisibleBySlugOrFail($slug);
        $this->checkOwnablePermission('book-update', $book);

        $validated = $this->validate($request, [
            'name'                => ['required', 'string', 'max:255'],
            'description_html'    => ['string', 'max:2000'],
            'image'               => array_merge(['nullable'], $this->getImageValidationRules()),
            'tags'                => ['array'],
            'default_template_id' => ['nullable', 'integer'],
        ]);

        if ($request->has('image_reset')) {
            $validated['image'] = null;
        } elseif (array_key_exists('image', $validated) && is_null($validated['image'])) {
            unset($validated['image']);
        }

        $book = $this->bookRepo->update($book, $validated);

        return redirect($book->getUrl());
    }

    /**
     * Shows the page to confirm deletion.
     */
    public function showDelete(string $bookSlug)
    {
        $book = $this->queries->findVisibleBySlugOrFail($bookSlug);
        $this->checkOwnablePermission('book-delete', $book);
        $this->setPageTitle(trans('entities.books_delete_named', ['bookName' => $book->getShortName()]));

        return view('books.delete', ['book' => $book, 'current' => $book]);
    }

    /**
     * Remove the specified book from the system.
     *
     * @throws Throwable
     */
    public function destroy(string $bookSlug)
    {
        $book = $this->queries->findVisibleBySlugOrFail($bookSlug);
        $this->checkOwnablePermission('book-delete', $book);

        $this->bookRepo->destroy($book);

        return redirect('/books');
    }

    /**
     * Show the view to copy a book.
     *
     * @throws NotFoundException
     */
    public function showCopy(string $bookSlug)
    {
        $book = $this->queries->findVisibleBySlugOrFail($bookSlug);
        $this->checkOwnablePermission('book-view', $book);

        session()->flashInput(['name' => $book->name]);

        return view('books.copy', [
            'book' => $book,
        ]);
    }

    /**
     * Create a copy of a book within the requested target destination.
     *
     * @throws NotFoundException
     */
    public function copy(Request $request, Cloner $cloner, string $bookSlug)
    {
        $book = $this->queries->findVisibleBySlugOrFail($bookSlug);
        $this->checkOwnablePermission('book-view', $book);
        $this->checkPermission('book-create-all');

        $newName = $request->get('name') ?: $book->name;
        $bookCopy = $cloner->cloneBook($book, $newName);
        $this->showSuccessNotification(trans('entities.books_copy_success'));

        return redirect($bookCopy->getUrl());
    }

    /**
     * Convert the chapter to a book.
     */
    public function convertToShelf(HierarchyTransformer $transformer, string $bookSlug)
    {
        $book = $this->queries->findVisibleBySlugOrFail($bookSlug);
        $this->checkOwnablePermission('book-update', $book);
        $this->checkOwnablePermission('book-delete', $book);
        $this->checkPermission('bookshelf-create-all');
        $this->checkPermission('book-create-all');

        $shelf = $transformer->transformBookToShelf($book);

        return redirect($shelf->getUrl());
    }
}
