<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$user = require_auth();
$profileError = '';
$passwordError = '';
$securityError = '';
$genderOptions = [
    '' => 'Nao informado',
    'feminino' => 'Feminino',
    'masculino' => 'Masculino',
    'outro' => 'Outro',
    'prefiro_nao_informar' => 'Prefiro nao informar',
];
$securityQuestions = security_questions();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $profileError = 'Sessao expirada. Atualize a pagina e tente novamente.';
    } elseif ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $birthDate = trim($_POST['birth_date'] ?? '');
        $isProfilePublic = isset($_POST['is_profile_public']) ? 1 : 0;
        $parsedBirthDate = $birthDate !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate) : null;
        $validBirthDate = $birthDate === '' || ($parsedBirthDate && $parsedBirthDate->format('Y-m-d') === $birthDate && $parsedBirthDate <= new DateTimeImmutable('today'));

        if ($name === '' || strlen($name) > 100) {
            $profileError = 'Informe um nome com ate 100 caracteres.';
        } elseif (!array_key_exists($gender, $genderOptions)) {
            $profileError = 'Selecione uma opcao valida para sexo.';
        } elseif (strlen($country) > 100 || strlen($state) > 100) {
            $profileError = 'Pais e estado devem ter ate 100 caracteres.';
        } elseif (!$validBirthDate) {
            $profileError = 'Informe uma data de nascimento valida.';
        } else {
            $stmt = db()->prepare('UPDATE users SET name = :name, gender = :gender, country = :country, state = :state, birth_date = :birth_date, is_profile_public = :is_profile_public WHERE id = :id');
            $stmt->execute([
                'name' => $name,
                'gender' => $gender !== '' ? $gender : null,
                'country' => $country !== '' ? $country : null,
                'state' => $state !== '' ? $state : null,
                'birth_date' => $birthDate !== '' ? $birthDate : null,
                'is_profile_public' => $isProfilePublic,
                'id' => $user['id'],
            ]);
            redirect('profile.php?saved=1');
        }
    } elseif ($action === 'change_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute(['id' => $user['id']]);
        $passwordHash = (string) $stmt->fetchColumn();

        if (!password_verify($currentPassword, $passwordHash)) {
            $passwordError = 'A senha atual esta incorreta.';
        } elseif (strlen($newPassword) < 6) {
            $passwordError = 'A nova senha deve ter pelo menos 6 caracteres.';
        } elseif ($newPassword !== $passwordConfirmation) {
            $passwordError = 'A confirmacao da nova senha nao confere.';
        } else {
            $stmt = db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
            $stmt->execute([
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'id' => $user['id'],
            ]);
            session_regenerate_id(true);
            redirect('profile.php?password_changed=1');
        }
    } elseif ($action === 'update_security_question') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $question = trim($_POST['security_question'] ?? '');
        $answer = trim($_POST['security_answer'] ?? '');
        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute(['id' => $user['id']]);
        $passwordHash = (string) $stmt->fetchColumn();

        if (!password_verify($currentPassword, $passwordHash)) {
            $securityError = 'A senha atual esta incorreta.';
        } elseif (!in_array($question, $securityQuestions, true)) {
            $securityError = 'Selecione uma das perguntas disponiveis.';
        } elseif (mb_strlen($answer) < 2 || mb_strlen($answer) > 150) {
            $securityError = 'Informe uma resposta com pelo menos 2 caracteres.';
        } else {
            $stmt = db()->prepare('UPDATE users SET security_question = :question, security_answer_hash = :answer_hash WHERE id = :id');
            $stmt->execute([
                'question' => $question,
                'answer_hash' => password_hash(mb_strtolower($answer), PASSWORD_DEFAULT),
                'id' => $user['id'],
            ]);
            redirect('profile.php?security_saved=1');
        }
    }

    $user = current_user() ?? $user;
}

app_shell_head('Meu perfil');
?>
<main class="mx-auto w-full max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-zinc-500">Sua conta</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-zinc-950">Meu perfil</h1>
        </div>
        <a href="dashboard.php" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-zinc-200 bg-white px-4 text-sm font-medium text-zinc-900 shadow-sm transition hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950">
            <i class="ph ph-arrow-left"></i> Voltar
        </a>
    </div>

    <?php if (isset($_GET['saved'])): ?>
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">Perfil atualizado.</div>
    <?php endif; ?>
    <?php if (isset($_GET['password_changed'])): ?>
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">Senha alterada com sucesso.</div>
    <?php endif; ?>
    <?php if (isset($_GET['security_saved'])): ?>
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">Pergunta de seguranca salva.</div>
    <?php endif; ?>

    <div class="grid gap-6 lg:grid-cols-[1.35fr_.85fr]">
        <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="mb-5 border-b border-zinc-200 pb-4">
                <h2 class="text-lg font-semibold tracking-tight text-zinc-900">Dados pessoais</h2>
                <p class="mt-1 text-sm text-zinc-500">Atualize as informacoes exibidas na sua conta.</p>
            </div>
            <?php if ($profileError): ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?= h($profileError) ?></div>
            <?php endif; ?>
            <form method="post" class="grid gap-4 sm:grid-cols-2">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="update_profile">
                <label class="block text-sm font-medium text-zinc-700 sm:col-span-2">Nome
                    <input name="name" required maxlength="100" value="<?= h($user['name']) ?>" class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-zinc-950">
                </label>
                <label class="block text-sm font-medium text-zinc-700 sm:col-span-2">Email
                    <input type="email" value="<?= h($user['email']) ?>" disabled class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-zinc-100 px-3 text-sm text-zinc-500">
                </label>
                <label class="block text-sm font-medium text-zinc-700">Sexo
                    <select name="gender" class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-zinc-950">
                        <?php foreach ($genderOptions as $value => $label): ?>
                            <option value="<?= h($value) ?>" <?= ($user['gender'] ?? '') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block text-sm font-medium text-zinc-700">Data de nascimento
                    <input name="birth_date" type="date" max="<?= date('Y-m-d') ?>" value="<?= h($user['birth_date'] ?? '') ?>" class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-zinc-950">
                </label>
                <label class="block text-sm font-medium text-zinc-700">Pais
                    <input name="country" maxlength="100" value="<?= h($user['country'] ?? '') ?>" class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-zinc-950" placeholder="Brasil">
                </label>
                <label class="block text-sm font-medium text-zinc-700">Estado
                    <input name="state" maxlength="100" value="<?= h($user['state'] ?? '') ?>" class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-zinc-950" placeholder="Sao Paulo">
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-4 sm:col-span-2">
                    <input name="is_profile_public" type="checkbox" value="1" <?= !empty($user['is_profile_public']) ? 'checked' : '' ?> class="mt-0.5 h-5 w-5 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-950">
                    <span>
                        <span class="block text-sm font-medium text-zinc-900">Exibir meu perfil no Feed Geral</span>
                        <span class="mt-1 block text-sm leading-5 text-zinc-500">Desative para ocultar seu nome, leituras e resenhas dos feeds da comunidade.</span>
                    </span>
                </label>
                <div class="flex justify-end sm:col-span-2">
                    <button class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-zinc-900 px-4 text-sm font-medium text-white shadow-sm hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2"><i class="ph ph-floppy-disk"></i> Salvar perfil</button>
                </div>
            </form>
        </section>

        <section class="self-start rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="mb-5 border-b border-zinc-200 pb-4">
                <h2 class="text-lg font-semibold tracking-tight text-zinc-900">Alterar senha</h2>
                <p class="mt-1 text-sm text-zinc-500">Confirme sua senha atual antes da troca.</p>
            </div>
            <?php if ($passwordError): ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?= h($passwordError) ?></div>
            <?php endif; ?>
            <form method="post" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="change_password">
                <label class="block text-sm font-medium text-zinc-700">Senha atual
                    <input name="current_password" type="password" required autocomplete="current-password" class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-zinc-950">
                </label>
                <label class="block text-sm font-medium text-zinc-700">Nova senha
                    <input name="new_password" type="password" minlength="6" required autocomplete="new-password" class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-zinc-950">
                </label>
                <label class="block text-sm font-medium text-zinc-700">Confirmar nova senha
                    <input name="password_confirmation" type="password" minlength="6" required autocomplete="new-password" class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-zinc-950">
                </label>
                <button class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-zinc-200 bg-white px-4 text-sm font-medium text-zinc-900 shadow-sm hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950"><i class="ph ph-key"></i> Atualizar senha</button>
            </form>
        </section>

        <section class="self-start rounded-xl border border-zinc-200 bg-white p-5 shadow-sm lg:col-span-2">
            <div class="mb-5 border-b border-zinc-200 pb-4">
                <h2 class="text-lg font-semibold tracking-tight text-zinc-900">Pergunta de seguranca</h2>
                <p class="mt-1 text-sm text-zinc-500">Usada para recuperar sua senha sem precisar de email. Guarde uma resposta que so voce saiba.</p>
            </div>
            <?php if ($securityError): ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?= h($securityError) ?></div>
            <?php endif; ?>
            <?php if (!empty($user['security_question'])): ?>
                <p class="mb-4 text-sm text-zinc-600">Pergunta atual: <span class="font-medium text-zinc-900"><?= h($user['security_question']) ?></span></p>
            <?php else: ?>
                <p class="mb-4 text-sm text-amber-700">Voce ainda nao configurou uma pergunta de seguranca.</p>
            <?php endif; ?>
            <form method="post" class="grid gap-4 sm:grid-cols-3">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="update_security_question">
                <label class="block text-sm font-medium text-zinc-700">Senha atual
                    <input name="current_password" type="password" required autocomplete="current-password" class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-zinc-950">
                </label>
                <label class="block text-sm font-medium text-zinc-700">Pergunta
                    <select name="security_question" required class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-zinc-950">
                        <option value="" disabled <?= empty($user['security_question']) ? 'selected' : '' ?>>Selecione uma pergunta</option>
                        <?php foreach ($securityQuestions as $option): ?>
                            <option value="<?= h($option) ?>" <?= ($user['security_question'] ?? '') === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block text-sm font-medium text-zinc-700">Resposta
                    <input name="security_answer" maxlength="150" required autocomplete="off" class="mt-2 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-zinc-950">
                </label>
                <div class="flex items-end sm:col-span-3 sm:justify-end">
                    <button class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-zinc-900 px-4 text-sm font-medium text-white shadow-sm hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2"><i class="ph ph-shield-check"></i> Salvar pergunta</button>
                </div>
            </form>
        </section>
    </div>
</main>
<?php app_shell_foot(); ?>