<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EmailNotifier;
use App\Services\MailSettingsService;
use App\Services\ServerToolService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class ServerToolController extends Controller
{
    public function __construct(
        private ServerToolService $tools,
        private MailSettingsService $mailSettings,
        private EmailNotifier $mail,
    ) {
    }

    /** Email settings: admin address, sender account and SMTP server, saved to .env. */
    public function saveMail(Request $request)
    {
        $data = $request->validate([
            'admin_address' => ['nullable', 'string', 'max:500', function ($attr, $value, $fail) {
                $parts = preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
                if (count($parts) !== count($this->mailSettings->addresses((string) $value))) {
                    $fail(__('Enter valid email addresses, separated by commas.'));
                }
            }],
            'mailer' => ['required', Rule::in(array_keys(MailSettingsService::MAILERS))],
            'host' => ['nullable', 'required_if:mailer,smtp', 'string', 'max:191', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'port' => ['nullable', 'required_if:mailer,smtp', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in(array_keys(MailSettingsService::ENCRYPTIONS))],
            'username' => ['nullable', 'string', 'max:191', 'not_regex:/[\r\n]/'],
            'password' => ['nullable', 'string', 'max:191', 'not_regex:/[\r\n]/'],
            'from_address' => ['required', 'email', 'max:191'],
            'from_name' => ['nullable', 'string', 'max:100', 'not_regex:/[\r\n]/'],
        ], [], [
            'admin_address' => __('Admin email'),
            'host' => __('SMTP server'),
            'from_address' => __('Sender email'),
        ]);

        try {
            $this->mailSettings->save($data, $request->user()->id);
        } catch (RuntimeException $e) {
            return redirect()->to(route('admin.tools.index') . '#mail')->withErrors(['mail' => $e->getMessage()])->withInput($request->except('password'));
        }

        return redirect()->to(route('admin.tools.index') . '#mail')->with('success', __('Email settings saved to .env.'));
    }

    /** Sends one email now with the saved settings and shows the mail server's answer if it fails. */
    public function testMail(Request $request)
    {
        $data = $request->validate(['to' => ['required', 'email', 'max:191']]);

        try {
            $this->mail->sendTest($data['to']);
        } catch (Throwable $e) {
            report($e);

            return redirect()->to(route('admin.tools.index') . '#mail')->withErrors(['mail_test' => __('The test email was not sent: :error', ['error' => mb_strimwidth($e->getMessage(), 0, 400, '…')])])->withInput();
        }

        $note = config('mail.default') === 'log' ? ' ' . __('Email is off, so it was written to laravel.log instead.') : '';

        return redirect()->to(route('admin.tools.index') . '#mail')->with('success', __('Test email sent to :to.', ['to' => $data['to']]) . $note);
    }

    public function index()
    {
        return view('admin.tools', [
            'tools' => $this->tools->tools(),
            'enabled' => $this->tools->enabled(),
            'summary' => $this->tools->summary(),
            'details' => $this->tools->details(),
            'info' => $this->tools->info(),
            'backups' => $this->tools->backups(),
            'logInfo' => $this->tools->logInfo(),
            'logEntries' => $this->tools->logEntries(),
            'logLines' => (int) config('server_tools.log_lines', 300),
            'mailSettings' => $this->mailSettings->current(),
            'mailers' => MailSettingsService::MAILERS,
            'encryptions' => MailSettingsService::ENCRYPTIONS,
        ]);
    }

    /** Runs one tool from the fixed list and shows its output on the page. */
    public function run(Request $request, string $tool)
    {
        abort_unless(array_key_exists($tool, $this->tools->tools()), 404);
        abort_unless($this->tools->enabled(), 403, 'Server Tools are switched off.');

        [$ok, $output, $seconds] = $this->tools->run($tool, $request->user()->id);

        return redirect()->route('admin.tools.index')->with('tool_result', [
            'tool' => $tool,
            'title' => $this->tools->tools()[$tool][0],
            'ok' => $ok,
            'output' => $output,
            'seconds' => $seconds,
        ]);
    }

    public function download(string $file)
    {
        $path = $this->tools->backupPath($file);
        abort_unless($path, 404);

        return response()->download($path, $file, ['Content-Type' => 'application/sql']);
    }

    public function destroy(Request $request, string $file)
    {
        abort_unless($this->tools->deleteBackup($file, $request->user()->id), 404);

        return back()->with('success', __('Backup deleted.'));
    }

    public function downloadLog()
    {
        abort_unless($this->tools->logInfo(), 404);

        return response()->download($this->tools->logPath(), 'laravel-' . now()->format('Y-m-d-His') . '.log', ['Content-Type' => 'text/plain']);
    }

    public function clearLog(Request $request)
    {
        $this->tools->clearLog($request->user()->id);

        return redirect()->to(route('admin.tools.index') . '#log')->with('success', __('laravel.log was cleared.'));
    }
}
