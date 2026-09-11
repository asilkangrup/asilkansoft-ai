<?php

$path = dirname(__DIR__).'/app/Filament/Resources/ConversationControls/Pages/ConversationInbox.php';

if (! is_file($path)) {
    fwrite(STDERR, "[inbox-owner-takeover] ConversationInbox not found.\n");
    exit(0);
}

$source = file_get_contents($path);
if (! is_string($source) || $source === '') {
    fwrite(STDERR, "[inbox-owner-takeover] Could not read ConversationInbox.\n");
    exit(1);
}

$original = $source;

if (! str_contains($source, 'protected function canControlSelectedConversation(): bool')) {
    $anchor = <<<'PHP'
    protected function canManageAssignments(): bool
    {
        return $this
            ->accessService()
            ->canAssignCustomers();
    }
PHP;

    $replacement = $anchor.<<<'PHP'


    protected function canControlSelectedConversation(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ((bool) ($user->is_admin ?? false)) {
            return true;
        }

        $conversation = $this->selectedConversation;

        if (! $conversation) {
            return false;
        }

        return (int) ($conversation->aiBot?->user_id ?? 0) === (int) $user->id
            || (int) ($conversation->user_id ?? 0) === (int) $user->id;
    }
PHP;

    if (! str_contains($source, $anchor)) {
        fwrite(STDERR, "[inbox-owner-takeover] Permission helper anchor missing.\n");
        exit(1);
    }

    $source = str_replace($anchor, $replacement, $source, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[inbox-owner-takeover] Unexpected permission helper replacement count: {$count}.\n");
        exit(1);
    }
}

$takeOverOld = <<<'PHP'
    public function takeOver(): void
    {
        if (! $this->canWriteInbox()) {
            abort(403);
        }
PHP;
$takeOverNew = <<<'PHP'
    public function takeOver(): void
    {
        if (! $this->canControlSelectedConversation()) {
            abort(403);
        }
PHP;
$source = str_replace($takeOverOld, $takeOverNew, $source);

$releaseOld = <<<'PHP'
    public function releaseToAi(): void
    {
        if (! $this->canWriteInbox()) {
            abort(403);
        }
PHP;
$releaseNew = <<<'PHP'
    public function releaseToAi(): void
    {
        if (! $this->canControlSelectedConversation()) {
            abort(403);
        }
PHP;
$source = str_replace($releaseOld, $releaseNew, $source);

$sendOld = <<<'PHP'
    public function sendMessage(
        WhatsAppService $whatsAppService
    ): void {
        if (! $this->canWriteInbox()) {
            abort(403);
        }
PHP;
$sendNew = <<<'PHP'
    public function sendMessage(
        WhatsAppService $whatsAppService
    ): void {
        if (! $this->canControlSelectedConversation()) {
            abort(403);
        }
PHP;
$source = str_replace($sendOld, $sendNew, $source);

if ($source === $original) {
    echo "[inbox-owner-takeover] already applied.\n";
    exit(0);
}

file_put_contents($path, $source);

$command = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1';
exec($command, $output, $exitCode);

if ($exitCode !== 0) {
    file_put_contents($path, $original);
    fwrite(STDERR, "[inbox-owner-takeover] Syntax check failed; original restored.\n".implode("\n", $output)."\n");
    exit(1);
}

echo "[inbox-owner-takeover] applied; syntax OK.\n";
