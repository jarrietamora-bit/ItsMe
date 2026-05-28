<?php if (!defined('BTSUPPORT')) exit; ?>
</main><!-- /main -->
</div><!-- /d-flex -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/main.js') ?>"></script>
<script>
const BASE_URL = '<?= base_url() ?>';
const CSRF_TOKEN = '<?= csrf_token() ?>';
</script>
<?php if (isset($extra_js)) echo $extra_js; ?>
<footer class="text-center text-muted py-2" style="font-size:11px;background:#fff;border-top:1px solid #e9ecef">
  <?= h(setting('company_name') ?: 'BT-Support') ?> &mdash; <?= t('footer_text') ?> &copy; <?= date('Y') ?>
</footer>
</body>
</html>
