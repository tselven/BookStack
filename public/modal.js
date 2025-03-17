document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('modal');
    const openModalBtn = document.getElementById('openModalBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const modalBox = document.getElementById('modal-cont');
    const prevPageBtn = document.getElementById('prevPageBtn');
    const nextPageBtn = document.getElementById('nextPageBtn');
    const flipbookContainer = document.createElement('div');
    flipbookContainer.classList.add('flipbook');
    let currentPage = 0;
    let totalPages = 0;

    // Open Modal and Fetch Book Data
    openModalBtn.addEventListener('click', function (event) {
        event.preventDefault();
        modal.style.display = 'flex';
        fetchBookData();
    });

    // Close Modal
    closeModalBtn.addEventListener('click', function () {
        modal.style.display = 'none';
    });

    // Fetch Book Data from API
    async function fetchBookData() {
        try {
            const id = openModalBtn.getAttribute('book-id');
            const apiUrl = openModalBtn.getAttribute('api-url');
            const response = await fetch(apiUrl+'/'+id);
            const data = await response.json();
            
            // Reset flipbook container
            flipbookContainer.innerHTML = '';
            modalBox.innerHTML = `<h1>${data.name}</h1><div class='text-muted'>${data.description || 'No description available'}</div>`;
            modalBox.appendChild(flipbookContainer);

            totalPages = data.pages.length;
            for (let i = 0; i < totalPages; i += 2) {
                const spread = document.createElement('div');
                spread.classList.add('spread');

                const leftPage = document.createElement('div');
                leftPage.classList.add('c-page');
                leftPage.dataset.index = i;
                leftPage.innerHTML = `<h2>${data.pages[i].name}</h2>${data.pages[i].html}`;

                spread.appendChild(leftPage);

                if (i + 1 < totalPages) {
                    const rightPage = document.createElement('div');
                    rightPage.classList.add('c-page');
                    rightPage.dataset.index = i + 1;
                    rightPage.innerHTML = `<h2>${data.pages[i + 1].name}</h2>${data.pages[i + 1].html}`;
                    spread.appendChild(rightPage);
                }

                flipbookContainer.appendChild(spread);
            }

            updatePageVisibility();
        } catch (error) {
            console.error('Error fetching book data:', error);
            modalBox.innerHTML = '<p class="text-muted">Failed to load book details.</p>';
        }
    }

    // Update Page Visibility
    function updatePageVisibility() {
        const spreads = document.querySelectorAll('.spread');
        spreads.forEach((spread, index) => {
            if (index === currentPage / 2) {
                spread.style.display = 'flex';
            } else {
                spread.style.display = 'none';
            }
        });
    }

    // Navigate Pages
    prevPageBtn.addEventListener('click', function () {
        if (currentPage > 0) {
            currentPage -= 2;
            updatePageVisibility();
        }
    });

    nextPageBtn.addEventListener('click', function () {
        if (currentPage + 2 < totalPages) {
            currentPage += 2;
            updatePageVisibility();
        }
    });

    // CSS Styles for Flip Effect
    const style = document.createElement('style');
    style.innerHTML = `
        .flipbook {
            position: relative;
            width: 100%;
            height: 400px;
            perspective: 1500px;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
        }

        .spread {
            display: flex;
            justify-content: space-between;
            width: 100%;
            height: 100%;
            position: absolute;
            transition: transform 1s ease-in-out;
        }

        .c-page {
            width: 50%;
            height: 100%;
            background: #feffb0;
            backface-visibility: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transform-style: preserve-3d;
            transition: transform 1s ease-in-out, box-shadow 0.3s ease-in-out;
            padding: 10px;
            box-sizing: border-box;
        }

        .c-page h2 {
            text-align: center;
            font-size: 20px;
        }

        .c-page:nth-child(odd) {
            box-shadow: -5px 0 20px rgba(0, 0, 0, 0.2);
        }

        .c-page:nth-child(even) {
            box-shadow: 5px 0 20px rgba(0, 0, 0, 0.2);
        }
    `;
    document.head.appendChild(style);
});
