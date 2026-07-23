<?php

namespace Modules\TaskManager\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\TaskManager\Http\Resources\AttachmentResource;
use Modules\TaskManager\Models\Attachment;
use Modules\TaskManager\Models\Task;

class AttachmentController extends Controller
{
    public function index(Task $task): JsonResponse
    {
        try {
            $this->authorize('view', $task);

            return response()->json([
                'message' => 'Attachments fetched successfully',
                'attachments' => AttachmentResource::collection($task->attachments),
            ]);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to view attachments for this task.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch attachments', ['task_id' => $task->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while fetching attachments.'], 500);
        }
    }

    public function store(Request $request, Task $task): JsonResponse
    {
        try {
            $this->authorize('update', $task);

            $request->validate([
                'file' => ['required', 'file', 'max:10240'],
            ], [
                'file.required' => 'Please select a file to upload.',
                'file.file' => 'The uploaded item is not a valid file.',
                'file.max' => 'The file size must not exceed 10MB.',
            ]);

            $attachment = DB::transaction(function () use ($request, $task) {
                $file = $request->file('file');
                $path = $file->store("attachments/task-{$task->id}", 'local');

                return $task->attachments()->create([
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ]);
            });

            return response()->json([
                'message' => 'File uploaded successfully',
                'attachment' => new AttachmentResource($attachment),
            ], 201);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to upload files to this task.'], 403);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'File upload validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to upload attachment', ['task_id' => $task->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while uploading the file.'], 500);
        }
    }

    public function destroy(Task $task, Attachment $attachment): JsonResponse
    {
        try {
            $this->authorize('delete', $attachment);

            DB::transaction(function () use ($attachment) {
                Storage::disk('local')->delete($attachment->path);
                $attachment->delete();
            });

            return response()->json(['message' => 'Attachment deleted successfully']);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You do not have permission to delete this attachment.'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to delete attachment', ['attachment_id' => $attachment->id, 'error' => $e]);

            return response()->json(['message' => 'Something went wrong while deleting the attachment.'], 500);
        }
    }
}
