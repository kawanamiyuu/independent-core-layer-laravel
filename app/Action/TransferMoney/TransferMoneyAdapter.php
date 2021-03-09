<?php
declare(strict_types=1);

namespace Service\Action\TransferMoney;

use Core\Account\Domain\Exceptions\NotFoundException;
use Core\Account\Domain\Models\Account;
use Core\Account\Domain\Models\AccountNumber;
use Core\Account\Domain\Models\Balance;
use Core\Account\Domain\Models\Transaction;
use Core\Account\UseCase\TransportMoney\TransferMoneyCommandPort;
use Core\Account\UseCase\TransportMoney\TransferMoneyQueryPort;
use Service\Eloquents\EloquentAccount;
use Service\Eloquents\EloquentTransaction;
use Service\Mail\TransferMoneyMail;
use Illuminate\Contracts\Mail\Mailer;

final class TransferMoneyAdapter implements
    TransferMoneyQueryPort,
    TransferMoneyCommandPort
{
    /** @var EloquentAccount */
    private $account;
    /** @var EloquentTransaction */
    private $transaction;
    /** @var Mailer */
    private $mailer;

    /**
     * @param EloquentAccount $account
     * @param EloquentTransaction $transaction
     * @param Mailer $mail
     */
    public function __construct(EloquentAccount $account, EloquentTransaction $transaction, Mailer $mail)
    {
        $this->account = $account;
        $this->transaction = $transaction;
        $this->mailer = $mail;
    }

    /**
     * @param AccountNumber $accountNumber
     * @return Account
     * @throws NotFoundException
     */
    public function findAndLockAccount(AccountNumber $accountNumber): Account
    {
        /** @var EloquentAccount $account */
        $account = $this->account->findByAccountNumberWithLockForUpdate($accountNumber);
        if (is_null($account)) {
            throw $this->notFoundException($accountNumber);
        }

        return $account->toModel();
    }

    /**
     * @param AccountNumber $accountNumber
     * @return Account
     * @throws NotFoundException
     */
    public function findAccount(AccountNumber $accountNumber): Account
    {
        /** @var EloquentAccount $account */
        $account = $this->account->findByAccountNumber($accountNumber);
        if (is_null($account)) {
            throw $this->notFoundException($accountNumber);
        }

        return $account->toModel();
    }

    /**
     * @param AccountNumber $accountNumber
     * @return NotFoundException
     */
    private function notFoundException(AccountNumber $accountNumber): NotFoundException
    {
        return new NotFoundException(sprintf('account_number %s not found', $accountNumber->__toString()));
    }

    /**
     * @param AccountNumber $accountNumber
     * @param Balance $balance
     */
    public function storeBalance(AccountNumber $accountNumber, Balance $balance): void
    {
        $this->account->updateBalance($accountNumber, $balance);
    }

    /**
     * @param Transaction $transaction
     */
    public function addTransaction(Transaction $transaction): void
    {
        $this->transaction->store($transaction);
    }

    /**
     * @param Account $account
     */
    public function notify(Account $account): void
    {
        $this->mailer->to($account->email()->asString())->send(new TransferMoneyMail($account));
    }
}
