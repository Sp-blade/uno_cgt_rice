<?php
	$auditId = (int) ($_GET['audit_id'] ?? $_POST['audit_id'] ?? 0);

	if ($auditId <= 0) {
		header('Location: ' . $server . '?mainmenu=inventory_audit&audit_status=error&audit_error=' . urlencode('Invalid audit record.'));
		exit;
	}

	$auditCheck = $connectDB->query("SELECT ID FROM inventory_audits WHERE ID = '$auditId' LIMIT 1");
	if (!$auditCheck || $auditCheck->num_rows === 0) {
		header('Location: ' . $server . '?mainmenu=inventory_audit&audit_status=error&audit_error=' . urlencode('Audit record not found.'));
		exit;
	}

	$connectDB->begin_transaction();
	try {
		if (!$connectDB->query("DELETE FROM inventory_audit_items WHERE Audit_ID = '$auditId'")) {
			throw new Exception('Unable to delete audit items.');
		}
		if (!$connectDB->query("DELETE FROM inventory_audits WHERE ID = '$auditId'")) {
			throw new Exception('Unable to delete audit record.');
		}
		$connectDB->commit();
		header('Location: ' . $server . '?mainmenu=inventory_audit&audit_status=deleted');
		exit;
	} catch (Exception $exception) {
		$connectDB->rollback();
		$errorMessage = trim($exception->getMessage());
		if ($errorMessage === '') {
			$errorMessage = 'Unable to delete audit record.';
		}
		header('Location: ' . $server . '?mainmenu=inventory_audit&audit_status=error&audit_error=' . urlencode($errorMessage));
		exit;
	}
