<?php
if (!defined('BTSUPPORT')) exit;
require_login();
$id = (int)($_GET['id'] ?? 0);
redirect(base_url('tickets/view' . ($id ? '?id=' . $id : '')));
