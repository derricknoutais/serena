<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Controller;
use App\Support\Frontdesk\RoomBoardData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoomBoardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Frontdesk/Rooms/Board', RoomBoardData::build($request));
    }
}
