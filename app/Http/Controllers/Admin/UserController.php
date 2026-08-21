<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::orderBy('name')->get();

        return view('admin.users.index', compact('users'));
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(UpdateUserRoleRequest $request, User $user): RedirectResponse
    {
        $user->update($request->validated());

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'อัปเดตสิทธิ์ผู้ใช้เรียบร้อยแล้ว');
    }
}
