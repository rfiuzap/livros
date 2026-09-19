<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

require_auth();
init_database();

app_shell_head('Banco de dados inicializado');
?>
<main class="mx-auto flex min-h-screen max-w-xl items-center px-6 py-12">
    <section class="w-full rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-lg border border-zinc-200 bg-zinc-50">
            <i class="ph ph-database text-2xl text-zinc-900"></i>
        </div>
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900">Banco pronto para uso</h1>
        <p class="mt-2 text-sm leading-6 text-zinc-500">As tabelas SQLite foram criadas ou verificadas em <span class="font-medium text-zinc-700">data/livro.sqlite</span>.</p>
        <a href="index.php" class="mt-6 inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-zinc-900 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
            <i class="ph ph-arrow-left"></i>
            Voltar ao app
        </a>
    </section>
</main>
<?php app_shell_foot(); ?>
