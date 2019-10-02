<h4 class="heading4"><?php echo $text_skip_bill_page; ?>:</h4>

<?php echo $form_open; ?>
  <?php echo $this->getHookVar('payment_table_pre'); ?>

  <div class="form-group form-inline">
    <div class="col-sm-10 input-group">
      <?php echo $billplz_skip_bill_page; ?>
    </div>
    <span class="help-block"></span>
  </div>
  <div class="form-group action-buttons text-center">
    <a id="<?php echo $button_back->name ?>" href="<?php echo $button_back->href; ?>" class="btn btn-default mr10 pull-left" title="<?php echo $button_back->text ?>">
      <i class="fa fa-arrow-left"></i>
      <?php echo $button_back->text ?>
    </a>
    <button type="submit" id="checkout_btn" class="btn btn-orange lock-on-click" title="<?php echo $button_confirm->name ?>" >
        <i class="fa fa-check"></i>
        <?php echo $button_confirm->name; ?>
    </button>
  </div>
</form>