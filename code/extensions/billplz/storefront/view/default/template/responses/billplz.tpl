<form action="<?php echo $action; ?>" method="get" id="checkout">
	<input type="hidden" name="auto_submit" value="<?php echo $autosubmit; ?>"/>
	<div class="form-group action-buttons">
	    <div class="col-md-12">
	    	<button id="checkout_btn" class="btn btn-orange pull-right" title="<?php echo $button_confirm; ?>">
	    	    <i class="fa fa-check"></i>
	    	    <?php echo $button_confirm; ?>
	    	</button>
	    	<a href="<?php echo str_replace('&', '&amp;', $back); ?>" class="btn btn-default" title="<?php echo button_back; ?>">
	    	    <i class="fa fa-arrow-left"></i>
	    	    <?php echo $button_back; ?>
	    	</a>
	    </div>
	</div>
</form>
