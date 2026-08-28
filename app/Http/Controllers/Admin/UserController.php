<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CloudInstance;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use RuntimeException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('q')->trim()->value();

        $users = User::query()
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->withCount(['orders', 'cloudInstances'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search'));
    }

    public function show(User $user)
    {
        $user->load([
            'orders' => fn ($q) => $q->with('sku')->latest(),
            'cloudInstances' => fn ($q) => $q->with('sku')->latest(),
            'walletTransactions' => fn ($q) => $q->limit(20),
        ]);

        // Devices in stock (not yet assigned to anyone) that can be handed to this user.
        $availableInstances = CloudInstance::unallocated()->latest()->get();

        return view('admin.users.show', compact('user', 'availableInstances'));
    }

    /** Manual balance credit/debit — refunds, goodwill, correcting a support mistake. */
    public function adjustBalance(Request $request, User $user, WalletService $wallet)
    {
        $data = $request->validate([
            'direction' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'note' => ['required', 'string', 'max:255'],
        ]);

        try {
            if ($data['direction'] === 'credit') {
                $wallet->credit($user, (float) $data['amount'], WalletTransaction::TYPE_ADJUSTMENT, $data['note']);
            } else {
                $wallet->debit($user, (float) $data['amount'], WalletTransaction::TYPE_ADJUSTMENT, $data['note']);
            }
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Balance updated.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // Set outside mass assignment — is_admin is deliberately not fillable.
        $user->is_admin = $request->boolean('is_admin');
        $user->email_verified_at = now();
        $user->save();

        return redirect()->route('admin.users.show', $user)->with('status', "Created {$user->email}.");
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        // Don't let the last admin (or yourself) accidentally drop admin rights
        // and lock everyone out of the panel.
        $removingOwnAdmin = $user->id === Auth::id() && ! $request->boolean('is_admin');
        $lastAdmin = $user->is_admin && ! $request->boolean('is_admin')
            && User::where('is_admin', true)->count() <= 1;

        if ($removingOwnAdmin || $lastAdmin) {
            return back()->with('error', 'You cannot remove the last admin account.');
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        $user->is_admin = $request->boolean('is_admin');

        if (filled($data['password'] ?? null)) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return back()->with('status', 'User updated.');
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->is_admin && User::where('is_admin', true)->count() <= 1) {
            return back()->with('error', 'You cannot delete the last admin account.');
        }

        // Devices return to stock rather than being deleted with the user.
        CloudInstance::where('user_id', $user->id)->update([
            'user_id' => null,
            'allocated_at' => null,
            'allocated_by' => null,
        ]);

        $email = $user->email;
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', "Deleted {$email}. Their devices were returned to stock.");
    }
}
