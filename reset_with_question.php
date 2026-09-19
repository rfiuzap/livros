<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$error = '';
$question = null;

$pending = $_SESSION['sq_pending'] ?? null;
if ($pending && (time() - $pending['started_at']) > 600) {
    unset($_SESSION['sq_pending']);
    $pending = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = $_POST['step'] ?? 'start';

    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Sessao expirada. Atualize a pagina e tente novamente.';
    } elseif ($step === 'start') {
        $email = trim($_POST['email'] ?? '');
        $ipIdentifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (too_many_attempts('security_reset_start', $ipIdentifier, 10, 3600)) {
            $error = 'Muitas tentativas deste endereco. Aguarde antes de tentar novamente.';
        } else {
            record_attempt('security_reset_start', $ipIdentifier);
            $stmt = db()->prepare('SELECT id, security_question FROM users WHERE email = :email');
            $stmt->execute(['email' => $email]);
            $found = $stmt->fetch();

            if ($found && !empty($found['security_question'])) {
                $_SESSION['sq_pending'] = ['user_id' => (int) $found['id'], 'started_at' => time()];
                $pending = $_SESSION['sq_pending'];
                $question = $found['security_question'];
            } else {
                $error = 'Nao foi possivel iniciar a recuperacao por esse metodo para este email. Confira o email digitado ou use a recuperacao por link.';
            }
        }
    } elseif ($step === 'answer' && $pending) {
        $answer = trim($_POST['security_answer'] ?? '');
        $newPassword = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        $answerIdentifier = ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|' . $pending['user_id'];

        if (too_many_attempts('security_answer', $answerIdentifier, 8, 1800)) {
            $error = 'Muitas tentativas. Aguarde antes de tentar novamente.';
            unset($_SESSION['sq_pending']);
            $pending = null;
        } else {
            $stmt = db()->prepare('SELECT security_question, security_answer_hash FROM users WHERE id = :id');
            $stmt->execute(['id' => $pending['user_id']]);
            $userRow = $stmt->fetch();
            $question = $userRow['security_question'] ?? null;

            if (!$userRow || !password_verify(mb_strtolower($answer), (string) $userRow['security_answer_hash'])) {
                record_attempt('security_answer', $answerIdentifier);
                $error = 'Resposta incorreta.';
            } elseif (strlen($newPassword) < 6) {
                $error = 'A nova senha deve ter pelo menos 6 caracteres.';
            } elseif ($newPassword !== $confirmation) {
                $error = 'As senhas nao conferem.';
            } else {
                db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id')->execute([
                    'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                    'id' => $pending['user_id'],
                ]);
                unset($_SESSION['sq_pending']);
                redirect('login.php?password_reset=1');
            }
        }
    }
}

app_shell_head('Recuperar senha');
?>
<main class="mx-auto flex min-h-screen max-w-sm items-center px-6 py-12">
    <section class="w-full rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <a href="login.php" class="mb-6 inline-flex items-center gap-2 text-sm font-medium text-zinc-500 hover:text-zinc-900"><i class="ph ph-arrow-left"></i> Entrar</a>
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900">Recuperar senha</h1>
        <p class="mt-2 text-sm text-zinc-500">Recuperacao por pergunta de seguranca, sem precisar de email.</p>
        <?php if ($error): ?><div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?= h($error) ?></div><?php endif; ?>

        <?php if ($question): ?>
            <form method="post" class="mt-6 space-y-4">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="step" value="answer">
                <p class="rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-700"><span class="font-medium">Pergunta:</span> <?= h($question) ?></p>
                <label class="block text-sm font-medium text-zinc-700">Resposta
                    <input name="security_answer" required autocomplete="off" class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-zinc-950">
                </label>
                <label class="block text-sm font-medium text-zinc-700">Nova senha
                    <input name="password" type="password" minlength="6" required class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-zinc-950">
                </label>
                <label class="block text-sm font-medium text-zinc-700">Confirmar nova senha
                    <input name="password_confirmation" type="password" minlength="6" required class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-zinc-950">
                </label>
                <button class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-zinc-900 px-4 text-sm font-medium text-white"><i class="ph ph-key"></i> Alterar senha</button>
            </form>
        <?php else: ?>
            <form method="post" class="mt-6 space-y-4">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="step" value="start">
                <label class="block text-sm font-medium text-zinc-700">Email
                    <input name="email" type="email" required class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-zinc-950">
                </label>
                <button class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-zinc-900 px-4 text-sm font-medium text-white"><i class="ph ph-shield-check"></i> Continuar</button>
            </form>
        <?php endif; ?>
    </section>
</main>
<?php app_shell_foot(); ?>
