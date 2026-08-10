<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Storefront', [
            'view' => 'admin-users',
            'users' => User::query()
                ->select(['id', 'first_name', 'last_name', 'phone_number', 'email', 'is_admin', 'created_at'])
                ->latest('id')
                ->paginate(10),
        ]);
    }
}
