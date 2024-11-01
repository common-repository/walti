<div class="wrap">
   <h2><a href="<?php echo esc_url( WALTI_URL ); ?>"><img src="<?php echo esc_url( plugins_url( 'images/walti_logo.png', dirname( __FILE__ ) ) ); ?>" alt="Waltiスキャン"></a></h2>

   <h2>Error</h2>
   <div class="walti-error">
     <?php echo $error->getMessage(); ?>
   </div>

   <pre class="walti-stacktrace""><?php echo $error->getTraceAsString(); ?></pre>

   <div><a href="#" onclick="javascript:window.history.back(-1);return false;">[back]</a></div>
</div>
