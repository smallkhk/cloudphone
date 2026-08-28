<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentsNotConfiguredException;
use App\Models\WalletDeposit;
use App\Services\Payments\WalletDepositService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        return view('wallet.index', [
            'balance' => $user->balance,
            'deposits' => $user->walletDeposits()->paginate(10, ['*'], 'deposits'),
            'transactions' => $user->walletTransactions()->paginate(10, ['*'], 'transactions'),
            'networksAvailable' => array_filter([
                'TRC20' => filled(config('crypto.usdt_trc20_address')),
                'BEP20' => filled(config('crypto.usdt_bep20_address')),
            ]),
        ]);
    }

    public function deposit(Request $request, WalletDepositService $deposits)
    {
        $data = $request->validate([
            'amount_usd' => ['required', 'numeric', 'min:5', 'max:100000'],
            'network' => ['required', 'in:TRC20,BEP20'],
        ]);

        try {
            $deposit = $deposits->create(Auth::user(), (float) $data['amount_usd'], $data['network']);
        } catch (PaymentsNotConfiguredException $e) {
            Log::error('wallet.deposit_not_configured', ['error' => $e->getMessage()]);

            return back()->with('error', Auth::user()->is_admin
                ? $e->getMessage()
                : 'Deposits are temporarily unavailable. Please contact support — we\'ve been notified.');
        }

        return redirect()->route('wallet.index')
            ->with('status', "Deposit created — send the exact USDT ({$deposit->network}) amount shown below.")
            ->with('newDepositId', $deposit->id);
    }

    public function submitTxHash(Request $request, WalletDeposit $walletDeposit, WalletDepositService $deposits)
    {
        abort_unless($walletDeposit->user_id === Auth::id(), 403);

        $validated = $request->validate([
            'tx_hash' => ['required', 'string', 'min:10', 'max:128'],
        ]);

        $deposits->submitTransactionHash($walletDeposit, trim($validated['tx_hash']));

        return redirect()->route('wallet.index')
            ->with('status', 'Thanks — we\'ll confirm your deposit on-chain shortly (usually within a couple of minutes).');
    }
}
