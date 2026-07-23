<?php

namespace Modules\TaskManager\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class TaskPageController extends Controller
{
    public function index(Request $request): View
    {
        return view('taskmanager::tasks', [
            'apiToken' => $this->issueToken($request),
            'me' => $request->user(),
        ]);
    }

    /**
     * Mint a fresh Sanctum token for the browser's axios calls to the JSON API.
     * Old web tokens are pruned so they don't accumulate per page load.
     */
    private function issueToken(Request $request): string
    {
        $request->user()->tokens()->where('name', 'tasks-web')->delete();

        return $request->user()->createToken('tasks-web')->plainTextToken;
    }
}
