<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$user = require_auth();
app_shell_head('Dashboard');
?>
<div class="min-h-screen">
    <header class="sticky top-0 z-30 border-b border-zinc-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
            <a href="dashboard.php" class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg border border-zinc-200 bg-white overflow-hidden"><img src="assets/logo_livro.png" alt="" class="h-7 w-7 object-contain"></span>
                <span class="font-semibold tracking-tight">Livro</span>
            </a>
            <nav class="flex items-center gap-2">
                <a href="profile.php" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg px-2 text-sm font-medium text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-950 focus:outline-none focus:ring-2 focus:ring-zinc-950" aria-label="Abrir perfil de <?= h($user['name']) ?>">
                    <i class="ph ph-user-circle text-xl"></i>
                    <span class="hidden sm:inline">Ola, <?= h($user['name']) ?></span>
                </a>
                <a href="logout.php" class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-900 shadow-sm transition hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2"><i class="ph ph-sign-out"></i> Sair</a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <section class="mb-6 grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
            <div>
                <p class="text-sm font-medium text-zinc-500">Gestao de leitura</p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight text-zinc-950">Sua estante e comunidade</h1>
            </div>
            <div class="flex items-center gap-2">
                <button id="openRegisteredBooks" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-zinc-200 bg-white px-4 text-sm font-medium text-zinc-900 shadow-sm transition hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                    <i class="ph ph-books"></i>
                    Livros Registrados
                </button>
                <button id="openBookPanel" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-zinc-900 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                    <i class="ph ph-plus"></i>
                    Registrar leitura
                </button>
            </div>
        </section>

        <section id="registeredBooksPanel" class="mb-8 hidden rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="mb-5 flex items-start justify-between gap-4 border-b border-zinc-200 pb-4">
                <div>
                    <h2 class="text-lg font-semibold tracking-tight">Livros registrados</h2>
                    <p class="mt-1 text-sm text-zinc-500">Todo o catalogo compartilhado, cadastrado por qualquer usuario. Clique em Editar para adicionar um livro a sua propria estante.</p>
                </div>
                <button id="closeRegisteredBooks" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-950" aria-label="Fechar"><i class="ph ph-x text-lg"></i></button>
            </div>
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <span class="text-xs font-medium text-zinc-500">Ordenar:</span>
                <button type="button" id="sortByTitle" class="h-8 rounded-lg border px-3 text-xs font-medium transition">Alfabetica</button>
                <button type="button" id="sortByRating" class="h-8 rounded-lg border px-3 text-xs font-medium transition">Nota</button>
                <button type="button" id="sortByAuthor" class="h-8 rounded-lg border px-3 text-xs font-medium transition">Autor</button>
                <span class="ml-3 text-xs font-medium text-zinc-500">Status:</span>
                <div id="registeredStatusFilter" class="flex flex-wrap gap-2"></div>
            </div>
            <div id="registeredBooksList" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"></div>
        </section>

        <section id="bookPanel" class="mb-8 hidden rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="mb-5 flex items-start justify-between gap-4 border-b border-zinc-200 pb-4">
                <div>
                    <h2 class="text-lg font-semibold tracking-tight">Ficha do livro</h2>
                    <p class="mt-1 text-sm text-zinc-500">Digite um titulo, busque dados automaticos e ajuste antes de salvar.</p>
                </div>
                <button id="closeBookPanel" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-950" aria-label="Fechar"><i class="ph ph-x text-lg"></i></button>
            </div>

            <form id="bookForm" class="grid gap-5 lg:grid-cols-[180px_1fr]">
                <div>
                    <div class="aspect-[2/3] overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100">
                        <img id="coverPreview" class="hidden h-full w-full object-cover" alt="Capa do livro">
                        <div id="coverFallback" class="flex h-full items-center justify-center text-zinc-400"><i class="ph ph-book-open text-4xl"></i></div>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <p id="formMessage" class="hidden text-sm text-zinc-500 md:col-span-2" aria-live="polite"></p>
                    <label class="block text-sm font-medium text-zinc-700 md:col-span-2">Titulo
                        <div class="mt-2 flex gap-2">
                            <input id="title" name="title" required class="h-10 min-w-0 flex-1 rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none transition placeholder:text-zinc-400 focus:ring-2 focus:ring-zinc-950" placeholder="Ex: Dom Casmurro">
                            <button type="button" id="searchBook" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-zinc-200 bg-white px-4 text-sm font-medium text-zinc-900 shadow-sm transition hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2"><i class="ph ph-magnifying-glass"></i> Buscar</button>
                        </div>
                    </label>
                    <label class="block text-sm font-medium text-zinc-700">Autor(es)
                        <input id="author" class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none transition focus:ring-2 focus:ring-zinc-950">
                    </label>
                    <label class="block text-sm font-medium text-zinc-700">Ano
                        <input id="release_year" type="number" class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none transition focus:ring-2 focus:ring-zinc-950">
                    </label>
                    <label class="block text-sm font-medium text-zinc-700">Paginas
                        <input id="pages" type="number" class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none transition focus:ring-2 focus:ring-zinc-950">
                    </label>
                    <label class="block text-sm font-medium text-zinc-700">Tema / Genero
                        <input id="genre" class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none transition focus:ring-2 focus:ring-zinc-950">
                    </label>
                    <label class="block text-sm font-medium text-zinc-700 md:col-span-2">URL da capa
                        <input id="cover_url" type="url" class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none transition focus:ring-2 focus:ring-zinc-950">
                    </label>
                    <label class="block text-sm font-medium text-zinc-700 md:col-span-2">Resumo / Sinopse
                        <textarea id="summary" rows="4" class="mt-2 w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm leading-6 outline-none transition focus:ring-2 focus:ring-zinc-950"></textarea>
                    </label>

                    <div class="md:col-span-2">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <label for="characterInput" class="text-sm font-medium text-zinc-700">Personagens principais</label>
                            <span id="characterStatus" class="text-xs text-zinc-500"></span>
                        </div>
                        <div class="rounded-lg border border-zinc-200 bg-white p-2 focus-within:ring-2 focus-within:ring-zinc-950">
                            <div id="characterTags" class="mb-2 flex flex-wrap gap-2"></div>
                            <input id="characterInput" class="h-8 w-full border-0 px-1 text-sm outline-none placeholder:text-zinc-400" placeholder="Adicionar personagem e pressionar Enter">
                        </div>
                    </div>

                    <div class="md:col-span-2 grid gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 md:grid-cols-2">
                        <div>
                            <p class="block text-sm font-medium text-zinc-700">Status</p>
                            <div id="statusOptions" class="mt-2 flex flex-wrap gap-2"></div>
                        </div>
                        <label class="block text-sm font-medium text-zinc-700">Data de leitura
                            <input id="read_at" type="date" class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none transition focus:ring-2 focus:ring-zinc-950">
                        </label>
                        <div>
                            <p class="text-sm font-medium text-zinc-700">Avaliacao</p>
                            <div id="ratingStars" class="mt-2 flex gap-1 text-2xl text-amber-500" aria-label="Selecionar avaliacao"></div>
                        </div>
                        <label class="block text-sm font-medium text-zinc-700 md:col-span-2">Meus comentarios
                            <textarea id="review" rows="3" class="mt-2 w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm leading-6 outline-none transition focus:ring-2 focus:ring-zinc-950"></textarea>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-3 md:col-span-2">
                        <button class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-zinc-900 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2"><i class="ph ph-check"></i> Salvar leitura</button>
                    </div>
                </div>
            </form>
        </section>

        <section class="grid gap-6 lg:grid-cols-[1fr_.9fr]">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                <div class="mb-5 flex items-center justify-between border-b border-zinc-200 pb-4">
                    <div>
                        <p class="text-sm font-medium text-zinc-500">Meus Livros</p>
                        <h2 class="text-lg font-semibold tracking-tight">Sua estante</h2>
                    </div>
                    <i class="ph ph-books text-2xl text-zinc-400"></i>
                </div>
                <div id="myBooks" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"></div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                <div class="mb-5 flex items-center justify-between border-b border-zinc-200 pb-4">
                    <div>
                        <p class="text-sm font-medium text-zinc-500">Comunidade</p>
                        <h2 class="text-lg font-semibold tracking-tight">Feed geral</h2>
                    </div>
                    <i class="ph ph-users text-2xl text-zinc-400"></i>
                </div>
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <h3 class="mb-3 text-sm font-semibold text-zinc-700">Por livro</h3>
                        <div id="feed" class="space-y-4"></div>
                    </div>
                    <div>
                        <h3 class="mb-3 text-sm font-semibold text-zinc-700">Por pessoa</h3>
                        <div id="feedByPerson" class="space-y-3"></div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<script>
const CSRF_TOKEN = <?= json_encode(csrf_token(), JSON_UNESCAPED_SLASHES) ?>;
const state = { characters: [], rating: 0, catalog: [], status: 'Quero ler', registeredSort: 'title', registeredStatusFilter: 'all' };
const STATUS_OPTIONS = ['Ja li', 'Lendo', 'Quero ler', 'Nao li'];
const $ = (id) => document.getElementById(id);

let messageDotsInterval = null;

function setMessage(text, tone = 'muted') {
    if (messageDotsInterval) {
        clearInterval(messageDotsInterval);
        messageDotsInterval = null;
    }

    const box = $('formMessage');
    const toneClass = tone === 'error'
        ? 'text-sm text-red-600'
        : tone === 'loading'
            ? 'text-sm font-semibold text-emerald-600'
            : 'text-sm text-zinc-500';
    box.className = `${text ? '' : 'hidden '}${toneClass} md:col-span-2`;

    if (tone === 'loading' && text) {
        const dots = ['.', '..', '...'];
        let index = 0;
        box.textContent = `${text} ${dots[index]}`;
        messageDotsInterval = setInterval(() => {
            index = (index + 1) % dots.length;
            box.textContent = `${text} ${dots[index]}`;
        }, 400);
        return;
    }

    box.textContent = text;
}

function renderStatusOptions() {
    $('statusOptions').innerHTML = '';
    STATUS_OPTIONS.forEach((value) => {
        const active = state.status === value;
        const label = document.createElement('label');
        label.className = `inline-flex h-10 cursor-pointer items-center gap-2 rounded-lg border px-3 text-sm font-medium transition focus-within:ring-2 focus-within:ring-zinc-950 ${active ? 'border-zinc-950 bg-zinc-900 text-white' : 'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50'}`;
        label.innerHTML = `<input type="radio" name="status" value="${value}" class="sr-only" ${active ? 'checked' : ''}>${value}`;
        label.querySelector('input').addEventListener('change', () => {
            state.status = value;
            renderStatusOptions();
        });
        $('statusOptions').appendChild(label);
    });
}

function renderStars() {
    $('ratingStars').innerHTML = '';
    for (let index = 1; index <= 5; index++) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'inline-flex h-9 w-9 items-center justify-center rounded-md transition hover:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-zinc-950';
        button.innerHTML = `<i class="${index <= state.rating ? 'ph-fill' : 'ph'} ph-star"></i>`;
        button.setAttribute('aria-label', `${index} estrela${index > 1 ? 's' : ''}`);
        button.addEventListener('click', () => { state.rating = state.rating === index ? 0 : index; renderStars(); });
        $('ratingStars').appendChild(button);
    }
}

function renderCharacters() {
    $('characterTags').innerHTML = '';
    state.characters.forEach((name) => {
        const tag = document.createElement('span');
        tag.className = 'inline-flex items-center gap-1 rounded-full border border-zinc-200 bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-800';
        tag.innerHTML = `${name}<button type="button" class="rounded-full p-0.5 hover:bg-zinc-200 focus:outline-none focus:ring-2 focus:ring-zinc-950" aria-label="Remover ${name}"><i class="ph ph-x text-xs"></i></button>`;
        tag.querySelector('button').addEventListener('click', () => {
            state.characters = state.characters.filter((item) => item !== name);
            renderCharacters();
        });
        $('characterTags').appendChild(tag);
    });
}

function addCharacter(name) {
    const clean = name.trim();
    if (clean && !state.characters.includes(clean)) {
        state.characters.push(clean);
        renderCharacters();
    }
}

function fillBook(book) {
    ['title', 'author', 'release_year', 'pages', 'genre', 'summary', 'cover_url'].forEach((field) => {
        $(field).value = book[field] || '';
    });
    const characters = parseCharacters(book.characters);
    if (characters.length) {
        state.characters = characters;
        renderCharacters();
    }
    updateCover();
}

function normalizeGoogleCover(url) {
    return (url || '').replace(/^http:/, 'https:').replace('zoom=1', 'zoom=0');
}

function googleBookPayload(item, fallbackTitle) {
    const info = item.volumeInfo || {};
    const yearMatch = String(info.publishedDate || '').match(/\d{4}/);
    return {
        title: info.title || fallbackTitle,
        author: (info.authors || []).join(', '),
        release_year: yearMatch ? Number(yearMatch[0]) : '',
        pages: info.pageCount || '',
        genre: (info.categories || [])[0] || '',
        summary: (info.description || '').replace(/<[^>]*>/g, '').trim(),
        cover_url: normalizeGoogleCover(info.imageLinks?.extraLarge || info.imageLinks?.large || info.imageLinks?.medium || info.imageLinks?.thumbnail || info.imageLinks?.smallThumbnail || ''),
        characters: [],
    };
}

function authorMatches(names, wanted) {
    return !!wanted && (names || []).some((name) => name.toLowerCase().includes(wanted.toLowerCase()));
}

async function searchBookInBrowser(query, author = '') {
    const searches = [];
    if (author) {
        searches.push(`intitle:${query} inauthor:${author}`);
    }
    searches.push(query, `intitle:${query}`, `${query} livro`, `${query} book`);

    for (const search of searches) {
        const response = await fetch(`https://www.googleapis.com/books/v1/volumes?q=${encodeURIComponent(search)}&maxResults=10&printType=books&orderBy=relevance`);
        if (!response.ok) {
            continue;
        }

        const data = await response.json();
        const items = Array.isArray(data.items) ? data.items : [];
        if (!items.length) {
            continue;
        }

        items.sort((a, b) => {
            const bookA = a.volumeInfo || {};
            const bookB = b.volumeInfo || {};
            const score = (book) => (authorMatches(book.authors, author) ? 10 : 0) + (book.title ? 4 : 0) + (book.authors?.length ? 3 : 0) + (book.description ? 2 : 0) + (book.imageLinks ? 1 : 0);
            return score(bookB) - score(bookA);
        });

        let selected = items[0];
        if (author) {
            selected = items.find((item) => authorMatches(item.volumeInfo?.authors, author));
            if (!selected) {
                continue;
            }
        }

        return googleBookPayload(selected, query);
    }

    let openLibraryUrl = `https://openlibrary.org/search.json?title=${encodeURIComponent(query)}&limit=5`;
    if (author) {
        openLibraryUrl += `&author=${encodeURIComponent(author)}`;
    }
    const openLibraryResponse = await fetch(openLibraryUrl);
    if (!openLibraryResponse.ok) {
        return null;
    }

    const openLibraryData = await openLibraryResponse.json();
    const docs = Array.isArray(openLibraryData.docs) ? openLibraryData.docs : [];
    const doc = author ? docs.find((candidate) => authorMatches(candidate.author_name, author)) : docs[0];
    if (!doc) {
        return null;
    }

    return {
        title: doc.title || query,
        author: (doc.author_name || []).join(', '),
        release_year: doc.first_publish_year || '',
        pages: doc.number_of_pages_median || '',
        genre: (doc.subject || [])[0] || '',
        summary: '',
        cover_url: doc.cover_i ? `https://covers.openlibrary.org/b/id/${encodeURIComponent(doc.cover_i)}-L.jpg` : '',
        characters: [],
    };
}

function updateCover() {
    const url = $('cover_url').value.trim();
    $('coverPreview').src = url;
    $('coverPreview').classList.toggle('hidden', !url);
    $('coverFallback').classList.toggle('hidden', !!url);
}

function starsHtml(rating) {
    const value = Number(rating || 0);
    return Array.from({ length: 5 }, (_, index) => `<i class="${index < value ? 'ph-fill' : 'ph'} ph-star"></i>`).join('');
}

function parseCharacters(value) {
    if (Array.isArray(value)) {
        return value;
    }
    try {
        const parsed = JSON.parse(value || '[]');
        return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
        return [];
    }
}

function emptyState(text) {
    return `<div class="rounded-lg border border-dashed border-zinc-200 p-6 text-center text-sm text-zinc-500">${text}</div>`;
}

function bookCard(book, options = {}) {
    const coverSize = options.portraitCover ? 'aspect-[2/3] w-full' : 'h-36 w-full';
    const cover = book.cover_url ? `<img src="${book.cover_url}" alt="Capa de ${book.title}" class="${coverSize} rounded-md object-cover">` : `<div class="flex ${coverSize} items-center justify-center rounded-md bg-zinc-100 text-zinc-400"><i class="ph ph-book-open text-3xl"></i></div>`;
    return `<article class="rounded-lg border border-zinc-200 bg-white p-3 shadow-sm">
        ${cover}
        <div class="mt-3 flex items-center justify-between gap-2"><span class="rounded-full border border-zinc-200 bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-800">${book.status}</span><span class="flex text-sm text-amber-500">${starsHtml(book.rating)}</span></div>
        <h3 class="mt-2 line-clamp-2 font-semibold leading-5 text-zinc-900">${book.title}</h3>
        <p class="mt-1 text-sm text-zinc-500">${book.author || 'Autor nao informado'}</p>
        ${book.read_at ? `<p class="mt-3 flex items-center gap-1 text-xs text-zinc-500"><i class="ph ph-calendar"></i>${book.read_at}</p>` : ''}
        <div class="mt-3 flex gap-2">
            <button type="button" data-edit-id="${book.id}" class="inline-flex h-8 flex-1 items-center justify-center gap-1.5 rounded-md border border-zinc-200 bg-white text-xs font-medium text-zinc-700 transition hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950"><i class="ph ph-pencil-simple"></i> Editar</button>
            <button type="button" data-delete-id="${book.id}" data-delete-title="${book.title}" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-zinc-200 bg-white text-red-600 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500" aria-label="Excluir livro"><i class="ph ph-trash"></i></button>
        </div>
    </article>`;
}

function sortBooks(books, sortBy) {
    const sorted = [...books];
    if (sortBy === 'rating') {
        sorted.sort((a, b) => (b.rating || 0) - (a.rating || 0) || a.title.localeCompare(b.title));
    } else if (sortBy === 'author') {
        sorted.sort((a, b) => (a.author || '').localeCompare(b.author || '') || a.title.localeCompare(b.title));
    } else {
        sorted.sort((a, b) => a.title.localeCompare(b.title));
    }
    return sorted;
}

const REGISTERED_STATUS_FILTERS = [
    { value: 'all', label: 'Todos' },
    { value: 'Ja li', label: 'Ja li' },
    { value: 'Lendo', label: 'Lendo' },
    { value: 'Quero ler', label: 'Quero ler' },
    { value: 'Nao li', label: 'Nao li' },
];

function renderRegisteredStatusFilter() {
    $('registeredStatusFilter').innerHTML = '';
    REGISTERED_STATUS_FILTERS.forEach(({ value, label }) => {
        const active = state.registeredStatusFilter === value;
        const optionLabel = document.createElement('label');
        optionLabel.className = `inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-lg border px-2.5 text-xs font-medium transition focus-within:ring-2 focus-within:ring-zinc-950 ${active ? 'border-zinc-950 bg-zinc-900 text-white' : 'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50'}`;
        optionLabel.innerHTML = `<input type="radio" name="registeredStatusFilter" value="${value}" class="sr-only" ${active ? 'checked' : ''}>${label}`;
        optionLabel.querySelector('input').addEventListener('change', () => {
            state.registeredStatusFilter = value;
            renderRegisteredBooks();
        });
        $('registeredStatusFilter').appendChild(optionLabel);
    });
}

function updateRegisteredControls() {
    const active = 'h-8 rounded-lg border border-zinc-950 bg-zinc-900 px-3 text-xs font-medium text-white transition';
    const inactive = 'h-8 rounded-lg border border-zinc-200 bg-white px-3 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50';
    $('sortByTitle').className = state.registeredSort === 'title' ? active : inactive;
    $('sortByRating').className = state.registeredSort === 'rating' ? active : inactive;
    $('sortByAuthor').className = state.registeredSort === 'author' ? active : inactive;
    renderRegisteredStatusFilter();
}

function renderRegisteredBooks() {
    let books = state.catalog;
    if (state.registeredStatusFilter !== 'all') {
        books = books.filter((book) => book.status === state.registeredStatusFilter);
    }
    books = sortBooks(books, state.registeredSort);
    $('registeredBooksList').innerHTML = books.length ? books.map((book) => bookCard(book, { portraitCover: true })).join('') : emptyState('Nenhum livro encontrado.');
    updateRegisteredControls();
}

function feedItem(book) {
    return `<article class="rounded-lg border border-zinc-200 p-4">
        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-zinc-600"><i class="ph ph-user"></i></div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2"><p class="font-medium text-zinc-900">${book.user_name}</p><span class="rounded-full border border-zinc-200 bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-800">${book.status}</span></div>
                <h3 class="mt-1 font-semibold text-zinc-900">${book.title}</h3>
                <div class="mt-1 flex text-sm text-amber-500">${starsHtml(book.rating)}</div>
                ${book.review ? `<p class="mt-3 text-sm leading-6 text-zinc-600">${book.review}</p>` : ''}
            </div>
        </div>
    </article>`;
}

function personItem(person) {
    const lastBook = person.last_book_title
        ? (person.last_book_status === 'Lendo'
            ? `<p class="mt-1 text-sm text-zinc-600">Lendo o livro <span class="font-medium text-zinc-900">${person.last_book_title}</span></p>`
            : `<p class="mt-1 text-sm text-zinc-600">Ultimo lido: <span class="font-medium text-zinc-900">${person.last_book_title}</span></p>`)
        : `<p class="mt-1 text-sm text-zinc-400">Nenhuma leitura registrada ainda</p>`;
    return `<article class="flex items-start gap-3 rounded-lg border border-zinc-200 p-4">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-zinc-600"><i class="ph ph-user"></i></div>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2"><p class="font-medium text-zinc-900">${person.user_name}</p><span class="rounded-full border border-zinc-200 bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-800">${person.total_read} livro${person.total_read === 1 ? '' : 's'} lido${person.total_read === 1 ? '' : 's'}</span></div>
            ${lastBook}
        </div>
    </article>`;
}

async function loadLists() {
    const [catalogResponse, feedResponse, feedByPersonResponse] = await Promise.all([
        fetch('api.php?action=catalog'),
        fetch('api.php?action=feed'),
        fetch('api.php?action=feed_by_person'),
    ]);
    const catalog = await catalogResponse.json();
    const feed = await feedResponse.json();
    const feedByPerson = await feedByPersonResponse.json();
    state.catalog = catalog.books || [];
    const shelfBooks = state.catalog.filter((book) => book.has_reading && book.status !== 'Nao li');
    $('myBooks').innerHTML = shelfBooks.length ? shelfBooks.map(bookCard).join('') : emptyState('Nenhum livro registrado ainda.');
    renderRegisteredBooks();
    $('feed').innerHTML = feed.books?.length ? feed.books.map(feedItem).join('') : emptyState('O feed ainda nao tem leituras.');
    $('feedByPerson').innerHTML = feedByPerson.people?.length ? feedByPerson.people.map(personItem).join('') : emptyState('O feed ainda nao tem leituras.');
}


function openBookForEdit(book) {
    $('registeredBooksPanel').classList.add('hidden');
    $('bookPanel').classList.remove('hidden');
    fillBook(book);
    state.status = book.status || 'Quero ler';
    renderStatusOptions();
    $('read_at').value = book.read_at || '';
    $('review').value = book.review || '';
    state.rating = book.rating || 0;
    renderStars();
    setMessage(`Editando "${book.title}". Ajuste os dados e salve novamente.`);
    $('bookPanel').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function handleEditClick(event) {
    const editButton = event.target.closest('[data-edit-id]');
    if (editButton) {
        const book = state.catalog.find((item) => String(item.id) === editButton.dataset.editId);
        if (book) {
            openBookForEdit(book);
        }
        return;
    }

    const deleteButton = event.target.closest('[data-delete-id]');
    if (deleteButton) {
        deleteRegisteredBook(deleteButton.dataset.deleteId, deleteButton.dataset.deleteTitle);
    }
}

async function deleteRegisteredBook(bookId, title) {
    if (!confirm(`Excluir "${title}" definitivamente? So funciona se ninguem tiver marcado esse livro como lido.`)) {
        return;
    }

    const response = await fetch('api.php?action=delete_book', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
        body: JSON.stringify({ book_id: Number(bookId) }),
    });
    const data = await response.json();

    if (!response.ok) {
        alert(data.error || 'Nao foi possivel excluir o livro.');
        return;
    }

    await loadLists();
}

$('myBooks').addEventListener('click', handleEditClick);
$('registeredBooksList').addEventListener('click', handleEditClick);

$('openBookPanel').addEventListener('click', () => $('bookPanel').classList.remove('hidden'));
$('closeBookPanel').addEventListener('click', () => $('bookPanel').classList.add('hidden'));
$('openRegisteredBooks').addEventListener('click', () => {
    $('registeredBooksPanel').classList.remove('hidden');
    $('registeredBooksPanel').scrollIntoView({ behavior: 'smooth', block: 'start' });
});
$('closeRegisteredBooks').addEventListener('click', () => $('registeredBooksPanel').classList.add('hidden'));
$('sortByTitle').addEventListener('click', () => { state.registeredSort = 'title'; renderRegisteredBooks(); });
$('sortByRating').addEventListener('click', () => { state.registeredSort = 'rating'; renderRegisteredBooks(); });
$('sortByAuthor').addEventListener('click', () => { state.registeredSort = 'author'; renderRegisteredBooks(); });
$('cover_url').addEventListener('input', updateCover);
$('characterInput').addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        addCharacter(event.target.value);
        event.target.value = '';
    }
});

$('searchBook').addEventListener('click', async () => {
    const query = $('title').value.trim();
    const authorHint = $('author').value.trim();
    if (!query) {
        setMessage('Digite um titulo para buscar.', 'error');
        return;
    }

    setMessage('Buscando dados do livro', 'loading');
    $('characterStatus').textContent = '';

    let book = null;
    const response = await fetch(`api.php?action=search_book&q=${encodeURIComponent(query)}&author=${encodeURIComponent(authorHint)}`);
    const data = await response.json();
    if (response.ok) {
        book = data.book;
    } else {
        setMessage('Tentando busca direta pelo navegador...');
        book = await searchBookInBrowser(query, authorHint);
    }

    if (!book) {
        setMessage(data.error || 'Nao foi possivel buscar o livro.', 'error');
        return;
    }

    fillBook(book);
    if (!state.characters.length) {
        $('characterStatus').textContent = 'Buscando personagens...';
        const characterResponse = await fetch(`api.php?action=characters&title=${encodeURIComponent($('title').value)}&author=${encodeURIComponent($('author').value)}`);
        const characterData = await characterResponse.json();
        state.characters = characterData.characters || [];
        renderCharacters();
    }
    $('characterStatus').textContent = state.characters.length ? 'Preenchido automaticamente' : 'Adicione manualmente se desejar';
    setMessage(data.source === 'local' ? 'Encontrado nos seus livros ja cadastrados.' : 'Dados preenchidos. Confira e salve sua leitura.');
});

$('bookForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const payload = {
        title: $('title').value,
        author: $('author').value,
        release_year: $('release_year').value,
        pages: $('pages').value,
        genre: $('genre').value,
        summary: $('summary').value,
        cover_url: $('cover_url').value,
        characters: state.characters,
        status: state.status,
        rating: state.rating,
        read_at: $('read_at').value,
        review: $('review').value,
    };

    setMessage('Salvando leitura...');
    const response = await fetch('api.php?action=save_reading', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
        body: JSON.stringify(payload),
    });
    const data = await response.json();

    if (!response.ok) {
        setMessage(data.error || 'Nao foi possivel salvar.', 'error');
        return;
    }

    event.target.reset();
    state.characters = [];
    state.rating = 0;
    state.status = 'Quero ler';
    renderCharacters();
    renderStars();
    renderStatusOptions();
    updateCover();
    $('bookPanel').classList.add('hidden');
    await loadLists();
});

renderStars();
renderCharacters();
renderStatusOptions();
loadLists();
</script>
<?php app_shell_foot(); ?>
