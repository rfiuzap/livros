<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = '';
$notice = '';

if (isset($_GET['registered'])) {
    $notice = 'Conta criada. Voce ja pode entrar.';
}
if (isset($_GET['password_reset'])) {
    $notice = 'Senha alterada. Entre com a nova senha.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $loginIdentifier = ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|' . strtolower($email);

    if ($email !== '' && too_many_attempts('login', $loginIdentifier, 10, 900)) {
        $error = 'Muitas tentativas. Aguarde alguns minutos e tente novamente.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            redirect('dashboard.php');
        } else {
            record_attempt('login', $loginIdentifier);
            $error = 'Email ou senha invalidos.';
        }
    }
}

app_shell_head('Entrar');
?>
<main class="mx-auto grid min-h-screen max-w-4xl items-center gap-10 px-6 py-12 lg:grid-cols-2">
    <div class="hidden lg:block">
        <img src="assets/logo_livro.png" alt="Livro - Gestao de Leitura" class="mx-auto w-full max-w-sm">
    </div>
    <section class="mx-auto w-full max-w-sm rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <a href="index.php" class="mb-6 inline-flex items-center gap-2 text-sm font-medium text-zinc-500 transition hover:text-zinc-900"><i class="ph ph-arrow-left"></i> Inicio</a>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900">Entrar</h1>
            <p class="mt-2 text-sm text-zinc-500">Acesse sua estante e o feed da comunidade.</p>
        </div>
        <?php if ($error): ?>
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?= h($error) ?></div>
        <?php endif; ?>
        <?php if ($notice): ?>
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700"><?= h($notice) ?></div>
        <?php endif; ?>
        <form method="post" class="space-y-4">
            <label class="block text-sm font-medium text-zinc-700">Email
                <input name="email" type="email" required class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none transition placeholder:text-zinc-400 focus:ring-2 focus:ring-zinc-950" placeholder="voce@email.com">
            </label>
            <label class="block text-sm font-medium text-zinc-700">Senha
                <input name="password" type="password" required class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none transition placeholder:text-zinc-400 focus:ring-2 focus:ring-zinc-950" placeholder="Sua senha">
            </label>
            <button class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-zinc-900 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                <i class="ph ph-user"></i>
                Entrar
            </button>
        </form>
        <div class="mt-5 space-y-2 text-center text-sm text-zinc-500">
            <p><a class="font-medium text-zinc-900 underline-offset-4 hover:underline" href="reset_with_question.php">Esqueci minha senha</a></p>
            <p>Ainda nao tem conta? <a class="font-medium text-zinc-900 underline-offset-4 hover:underline" href="register.php">Cadastre-se</a></p>
        </div>
    </section>
</main>
<?php app_shell_foot(); ?>
