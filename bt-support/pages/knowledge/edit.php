<?php
if (!defined('BTSUPPORT')) exit;
require_role(['super_admin', 'admin', 'agent']);
$id = (int)($_GET['id'] ?? 0);
redirect(base_url('knowledge/manage' . ($id ? '?edit=' . $id : '')));
