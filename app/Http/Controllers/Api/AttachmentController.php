<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function index(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return response()->json([
            'message'     => 'Attachments fetched successfully',
            'attachments' => AttachmentResource::collection($task->attachments),
        ]);
    }

    public function store(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10 MB
        ]);

        $file = $request->file('file');
        $path = $file->store("attachments/task-{$task->id}", 'local');

        $attachment = $task->attachments()->create([
            'original_name' => $file->getClientOriginalName(),
            'path'          => $path,
            'size'          => $file->getSize(),
            'mime_type'     => $file->getMimeType(),
        ]);

        return response()->json([
            'message'    => 'File uploaded successfully',
            'attachment' => new AttachmentResource($attachment),
        ], 201);
    }

    public function destroy(Task $task, Attachment $attachment): JsonResponse
    {
        $this->authorize('delete', $attachment);

        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted successfully']);
    }
}
