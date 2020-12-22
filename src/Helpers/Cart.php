<?php
namespace Imarc\Caprice;

use iMarc\Common\Commerce\Cart as CommerceCart;
use iMarc\Common\Commerce\PaymentGateway;

use \Exception;

class Cart extends CommerceCart
{
	/**
	 * Transaction states
	 */
	const STATE_INITIALIZED = 'Started';
	const STATE_CHARGED     = 'Charged';
	const STATE_STORED      = 'Stored';
	const STATE_COMPLETE    = 'Completed';

	const STATE_FAILED      = 'Failed';

	const MSG_DUPLICATE_TRANSACTION = 'A similar transaction already exists.';

	/**
	 * Checkout
	 */
	public function checkout(PaymentGateway $payment_gateway, $trx_type = 'Default')
	{
		$this->transaction = $this->initializeInventoryTransaction($payment_gateway, $trx_type);
		if ($this->transaction && $this->transaction instanceof Stateable) {
			if ($this->findTransaction($this->transaction->makeTransactionCompareKey())) {
				$this->transaction->clear();
				$e = new Exception(self::MSG_DUPLICATE_TRANSACTION);
				$this->rollbackPaymentTransaction($payment_gateway, $trx_type, $e);
			}
			$this->transaction->updateTransactionState(self::STATE_INITIALIZED);
		}

		foreach(array_keys($this->getTransactionAmounts()) as $group) {
			try {
				$this->referenceNumbers[$group] = $payment_gateway->executeTransaction($group);

				if ($this->transaction && $this->transaction instanceof Stateable) {
					$this->transaction->updateTransactionState(self::STATE_CHARGED . ': ' . $group);
				}
			} catch (Exception $e) {
				if ($this->transaction && $this->transaction instanceof Stateable) {
					$this->transaction->updateTransactionState(self::STATE_FAILED . ': ' . $e->getMessage());
				}
				$this->rollbackPaymentTransaction($payment_gateway, $trx_type, $e);
			}
		}

		try {
			$items = array();

			foreach (array_keys($this->items) as $type) {
				foreach ($this->items[$type] as $item) {
					$items[] = $item;
				}
			}

			usort($items, function($item_a, $item_b) {
				$priority_a = $item_a->fetchPurchasePriority();
				$priority_b = $item_b->fetchPurchasePriority();

				if ($priority_a == $priority_b) {
					return 0;
				}

				return $priority_a < $priority_b
					? -1
					: 1;
			});

			$this->startInventoryTransaction($payment_gateway, $trx_type);

			foreach ($items as $item) {
				$item->setCart($this);
				$item->purchase($this->getTransaction(), $this->getReferenceNumber($item));
			}

			if ($this->transaction && $this->transaction instanceof Stateable) {
				$this->transaction->updateTransactionState(self::STATE_STORED);
			}

			$this->commitInventoryTransaction($payment_gateway, $trx_type);

			if ($this->transaction && $this->transaction instanceof Stateable) {
				$this->transaction->updateTransactionState(self::STATE_COMPLETE);
			}

			return $this->transaction;

		} catch (Exception $e) {
			if ($this->transaction && $this->transaction instanceof Stateable) {
				$this->transaction->updateTransactionState(self::STATE_FAILED . ': ' . $e->getMessage());
			}
			$this->rollbackInventoryTransaction($payment_gateway, $trx_type, $e);
		}
	}

	/**
	 * Overload to support transactions
	 *
	 */
	protected function initializeInventoryTransaction($payment_gateway,  $trx_type)
	{

	}

	/**
	 * 
	 */
	protected function findTransaction($key)
	{
		return NULL;
	}
}