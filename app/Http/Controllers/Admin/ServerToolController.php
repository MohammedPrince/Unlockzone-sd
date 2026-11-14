<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ServerToolService;
use Illuminate\Http\Request;

class ServerToolController extends Controller
{
    public function __construct(private ServerToolService $tools)
    {
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
