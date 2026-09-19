<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if (current_user()) {
    redirect('dashboard.php');
}

$mostReadBooks = db()->query(<<<SQL
    SELECT
        b.id,
        b.title,
        b.author,
        b.genre,
        b.summary,
        b.cover_url,
        COUNT(DISTINCT ub.user_id) AS reader_count,
        AVG(ub.rating) AS average_rating
    FROM books b
    LEFT JOIN user_books ub ON ub.book_id = b.id AND ub.status = 'Ja li'
    GROUP BY b.id
    ORDER BY reader_count DESC, average_rating DESC, b.created_at DESC
    LIMIT 2
SQL)->fetchAll();

app_shell_head('Gestao de Leitura de Livros');
?>
<main class="mx-auto grid min-h-screen max-w-6xl items-center gap-10 px-6 py-12 lg:grid-cols-[1.05fr_.95fr]">
    <section>
        <img src="assets/logo_livro.png" alt="Livro" class="mb-5 h-16 w-16">
        <h1 class="max-w-2xl text-4xl font-bold tracking-tight text-zinc-950 sm:text-5xl">Gestao completa da sua leitura de livros.</h1>
        <p class="mt-5 max-w-xl text-base leading-7 text-zinc-500">Cadastre os livros que ja leu, esta lendo ou quer ler. Acompanhe paginas, genero, resumo, personagens e sua nota — tudo numa estante pessoal conectada a comunidade.</p>
        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
            <a href="register.php" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-zinc-900 px-5 text-sm font-medium text-white shadow-sm transition hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                <i class="ph ph-plus"></i>
                Criar conta
            </a>
            <a href="login.php" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-zinc-200 bg-white px-5 text-sm font-medium text-zinc-900 shadow-sm transition hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                <i class="ph ph-user"></i>
                Entrar
            </a>
        </div>
    </section>

    <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between border-b border-zinc-200 pb-4">
            <div>
                <p class="text-sm font-medium text-zinc-500">Feed da comunidade</p>
                <h2 class="text-xl font-semibold tracking-tight">Livros mais lidos</h2>
            </div>
            <span class="rounded-full border border-zinc-200 bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-800">Comunidade</span>
        </div>
        <div class="mt-5 space-y-4">
            <?php if ($mostReadBooks): ?>
                <?php foreach ($mostReadBooks as $book): ?>
                    <?php
                    $coverUrl = filter_var($book['cover_url'] ?? '', FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//i', $book['cover_url']) ? $book['cover_url'] : '';
                    $rating = $book['average_rating'] !== null ? (float) $book['average_rating'] : 0;
                    $readerCount = (int) $book['reader_count'];
                    ?>
                    <article class="flex gap-4 rounded-lg border border-zinc-200 p-4">
                        <div class="flex h-24 w-16 shrink-0 items-center justify-center overflow-hidden rounded-md bg-zinc-100">
                            <?php if ($coverUrl): ?>
                                <img src="<?= h($coverUrl) ?>" alt="Capa de <?= h($book['title']) ?>" class="h-full w-full object-cover">
                            <?php else: ?>
                                <i class="ph ph-book-open text-2xl text-zinc-500"></i>
                            <?php endif; ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 text-sm">
                                <div class="flex text-amber-500" aria-label="Avaliacao media <?= h(number_format($rating, 1, ',', '.')) ?> de 5">
                                    <?php for ($star = 1; $star <= 5; $star++): ?><i class="<?= $star <= round($rating) ? 'ph-fill' : 'ph' ?> ph-star"></i><?php endfor; ?>
                                </div>
                                <?php if ($rating > 0): ?><span class="text-zinc-500"><?= h(number_format($rating, 1, ',', '.')) ?></span><?php endif; ?>
                            </div>
                            <h3 class="mt-1 truncate font-semibold text-zinc-900"><?= h($book['title']) ?></h3>
                            <p class="truncate text-sm text-zinc-500"><?= h($book['author'] ?: 'Autor nao informado') ?></p>
                            <p class="mt-2 text-xs font-medium text-zinc-700"><?= $readerCount ?> leitor<?= $readerCount === 1 ? '' : 'es' ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="rounded-lg border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-500">Nenhum livro cadastrado ainda.</div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php app_shell_foot(); ?>
