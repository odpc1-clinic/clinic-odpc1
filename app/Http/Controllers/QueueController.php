<?php

namespace App\Http\Controllers;

use App\Models\Queue;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    public function createQueue(Request $request)
    {
        $queue = new Queue;
        $queue->room = $request->input('room');
        $queue->queue_number = Queue::where('room', $request->input('room'))->count() + 1;
        $queue->save();

        return response()->json($queue);
    }

    public function getQueues()
    {
        return response()->json(Queue::all());
    }
}
