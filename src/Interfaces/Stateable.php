<?php
namespace Imarc\Caprice;

interface Stateable {
	/**
	 * Update transaction state
	 */
	public function updateTransactionState($state);

	/**
	 * Clear out current transaction
	 */
	public function clear();

	/**
	 * Get a key to compare to other transactions
	 */
	public function makeTransactionCompareKey();
}