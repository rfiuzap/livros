<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = '';
$securityQuestions = security_questions();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $securityQuestion = trim($_POST['security_question'] ?? '');
    $securityAnswer = trim($_POST['security_answer'] ?? '');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        $error = 'Informe nome, email valido e senha com pelo menos 6 caracteres.';
    } elseif (!in_array($securityQuestion, $securityQuestions, true)) {
        $error = 'Selecione uma pergunta de seguranca.';
    } elseif (mb_strlen($securityAnswer) < 2 || mb_strlen($securityAnswer) > 150) {
        $error = 'Informe uma resposta de seguranca com pelo menos 2 caracteres.';
    } else {
        try {
            $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, security_question, security_answer_hash) VALUES (:name, :email, :password_hash, :security_question, :security_answer_hash)');
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'security_question' => $securityQuestion,
                'security_answer_hash' => password_hash(mb_strtolower($securityAnswer), PASSWORD_DEFAULT),
            ]);

            redirect('login.php?registered=1');
        } catch (PDOException $exception) {
            $error = str_contains($exception->getMessage(), 'UNIQUE') ? 'Este email ja esta cadastrado.' : 'Nao foi possivel criar a conta.';
        }
    }
}

app_shell_head('Cadastro');
?>
<main class="mx-auto grid min-h-screen max-w-4xl items-center gap-10 px-6 py-12 lg:grid-cols-2">
    <div class="hidden lg:block">
        <img src="assets/logo_livro.png" alt="Livro - Gestao de Leitura" class="mx-auto w-full max-w-sm">
    </div>
    <section class="mx-auto w-full max-w-sm rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <a href="index.php" class="mb-6 inline-flex items-center gap-2 text-sm font-medium text-zinc-500 transition hover:text-zinc-900"><i class="ph ph-arrow-left"></i> Inicio</a>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900">Criar conta</h1>
            <p class="mt-2 text-sm text-zinc-500">Registre leituras e compartilhe suas resenhas.</p>
        </div>
        <?php if ($error): ?>
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?= h($error) ?></div>
        <?php endif; ?>
        <form method="post" class="space-y-4">
            <label class="block text-sm font-medium text-zinc-700">Nome
                <input name="name" required class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none transition placeholder:text-zinc-400 focus:ring-2 focus:ring-zinc-950" placeholder="Seu nome">
            </label>
            <label class="block text-sm font-medium text-zinc-700">Email
                <input name="email" type="email" required class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none transition placeholder:text-zinc-400 focus:ring-2 focus:ring-zinc-950" placeholder="voce@email.com">
            </label>
            <label class="block text-sm font-medium text-zinc-700">Senha
                <input name="password" type="password" minlength="6" required class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none transition placeholder:text-zinc-400 focus:ring-2 focus:ring-zinc-950" placeholder="Minimo 6 caracteres">
            </label>
            <label class="block text-sm font-medium text-zinc-700">Pergunta de seguranca
                <select name="security_question" required class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none transition focus:ring-2 focus:ring-zinc-950">
                    <option value="" disabled selected>Selecione uma pergunta</option>
                    <?php foreach ($securityQuestions as $option): ?>
                        <option value="<?= h($option) ?>"><?= h($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block text-sm font-medium text-zinc-700">Resposta
                <input name="security_answer" required autocomplete="off" class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none transition placeholder:text-zinc-400 focus:ring-2 focus:ring-zinc-950" placeholder="Guarde uma resposta que so voce saiba">
            </label>
            <p class="text-xs text-zinc-500">Usada para recuperar sua senha caso esqueca, sem precisar de email.</p>
            <button class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-zinc-900 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                <i class="ph ph-plus"></i>
                Criar conta
            </button>
        </form>
        <p class="mt-5 text-center text-sm text-zinc-500">Ja tem conta? <a class="font-medium text-zinc-900 underline-offset-4 hover:underline" href="login.php">Entrar</a></p>
    </section>
</main>
<?php app_shell_foot(); ?>
