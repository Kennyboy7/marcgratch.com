<?php !defined('ABSPATH') && exit ?>
<input type="radio" name="<?php $this->field_name('enabled') ?>" autocomplete="off" id="<?php $this->field_id('enabled-1') ?>" value="1" <?php checked(@$settings->enabled, 1) ?>/><label for="<?php $this->field_id('enabled-1') ?>">Enabled</label>
<input type="radio" name="<?php $this->field_name('enabled') ?>" autocomplete="off" id="<?php $this->field_id('enabled-0') ?>" value="0" <?php checked(@$settings->enabled, 0) ?>/><label for="<?php $this->field_id('enabled-0') ?>">Disabled</label>                        