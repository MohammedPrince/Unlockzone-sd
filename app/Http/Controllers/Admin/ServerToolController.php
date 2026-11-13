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
            'info' => $this->tools->info(),
            'backups' => $this->tools->backups(),
        ]);
    }

    /** Runs one tool from the fixed list and shows its output on the page. */
    public function run(Request $request, string $tool)
    {
        abort_unless(array_key_exists($tool, $this->tools->tools()), 404);

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
}
