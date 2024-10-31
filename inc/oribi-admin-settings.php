<?php

$default_capabilities = array(
    'completed' => true
);

function oribi_base64decode( $input ) {
    return base64_decode( $input );
}

function oribi_register_settings() {
    global $default_capabilities;
    add_option( 'oribi_snippet' );
    add_option( 'oribi_tracking_capabilities', $default_capabilities );
    register_setting( 'oribi_options_group', 'oribi_snippet', array( 'sanitize_callback' => 'oribi_base64decode' ) );
    register_setting( 'oribi_options_group', 'oribi_tracking_capabilities' );
}
add_action( 'admin_init', 'oribi_register_settings' );

function oribi_register_options_page() {
    add_options_page( 'Oribi Analytics Settings', 'Oribi Analytics', 'manage_options', 'oribi', 'oribi_options_page_html' );
}
add_action( 'admin_menu', 'oribi_register_options_page' );

function oribi_base64encode() {
?>
<script type="text/javascript">
    var oribi_submit = document.getElementById( 'submit' )
    var oribi_snippet_unencoded = document.getElementById( 'oribi_snippet_unencoded' );
    oribi_submit.disabled = oribi_snippet_unencoded.value === '';

    oribi_submit.addEventListener( 'click', function() {
        var oribi_snippet = document.getElementById( 'oribi_snippet' );
        oribi_snippet.value = b64EncodeUnicode( oribi_snippet_unencoded.value );
    });

    function b64EncodeUnicode(str) {
        return btoa(encodeURIComponent(str).replace(/%([0-9A-F]{2})/g, function(match, p1) {
            return String.fromCharCode('0x' + p1);
        }));
    }

    oribi_snippet_unencoded.addEventListener( 'input', function() {
        oribi_submit.disabled = oribi_snippet_unencoded.value === '';
    }, false);
</script>
<?php
}
add_action( 'admin_footer', 'oribi_base64encode' );

function oribi_email_checkbox_toggle_js() {
?>
<script type="text/javascript">
    function checkbox_toggle_label(checkboxName) {
        var oribi_checkbox = document.getElementById( 'oribi-' + checkboxName + '-checkbox' );
        oribi_checkbox.addEventListener( 'change', function() {
            var checkbox_title = document.getElementsByClassName( 'oribi-' + checkboxName + '-checkbox-title' )[ 0 ];
            var status_on_text = checkbox_title.querySelector( '.oribi-' + checkboxName + '-status-on' );
            var status_off_text = checkbox_title.querySelector( '.oribi-' + checkboxName + '-status-off' );
            if( oribi_checkbox.checked ) {
                status_on_text.style.display = 'initial';
                status_off_text.style.display = 'none';
            } else {
                status_on_text.style.display = 'none';
                status_off_text.style.display = 'initial';
            }
        });
    }
    checkbox_toggle_label('email');
    checkbox_toggle_label('completed');
</script>
<?php
}
add_action( 'admin_footer', 'oribi_email_checkbox_toggle_js' );

function oribi_options_page_html() {
    $tracking_capabilities = Oribi_Event_Tracker::get_tracking_capabilities();
    ?>
    <div id="oribi-wrap">
        <div id="oribi-logo">
            <img src="<?php echo esc_url( plugins_url( 'images/oribi.svg', dirname( __FILE__ ) ) ); ?>" width="51" height="73" alt="Oribi logo" />
        </div>
        <div>
            <h2><?php esc_html_e( 'Oribi Analytics for WooCommerce', 'oribi' ); ?></h2>
            <h3>Connect visitor behavior with your online purchases, optimize your sales and grow your store.</h3>
            <form method="post" action="options.php">
                <?php settings_fields( 'oribi_options_group' ); ?>
                <h4>Tracking code</h4>
                <p>Paste your Oribi tracking code and click <span class="oribi-medium-font">Save Changes</span>. If you don’t have an Oribi account yet, create one for free <a target="_blank" href="https://oribi.io/login">here</a>.</p>
                <textarea id="oribi_snippet_unencoded" rows="8"><?php echo get_option( 'oribi_snippet' ); ?></textarea>
                <input type="hidden" id="oribi_snippet" name="oribi_snippet" />
                <div id="oribi-form-checkbox">
                    <h4>Email integration</h4>
                    <label class="switch" for="oribi-email-checkbox">
                        <input type="checkbox" id="oribi-email-checkbox" name="oribi_tracking_capabilities[email]" value="1" <?php checked( 1 == $tracking_capabilities[ 'email' ] ); ?> />
                        <span class="slider round"></span>
                    </label>
                    <p class="oribi-switch-title oribi-email-checkbox-title">Email integration feature is
                        <span class="oribi-email-status-on oribi-medium-font" style="display: <?php echo ( 1 == $tracking_capabilities[ 'email' ] ? 'initial' : 'none' ); ?>">connected</span>
                        <span class="oribi-email-status-off oribi-medium-font" style="display: <?php echo ( 0 == $tracking_capabilities[ 'email' ] ? 'initial' : 'none' ); ?>">disconnected</span>
                    </p>
                    <p class="oribi-switch-description">Oribi’s email integration feature allows you to see the website journeys of your top visitors and identify common patterns.</p>
                </div>
                <div id="oribi-form-checkbox">
                    <h4>Purchase Tracking</h4>
                    <label class="switch" for="oribi-completed-checkbox">
                        <input type="checkbox" id="oribi-completed-checkbox" name="oribi_tracking_capabilities[completed]" value="1" <?php checked( 1 == $tracking_capabilities[ 'completed' ] ); ?> />
                        <span class="slider round"></span>
                    </label>
                    <p class="oribi-switch-title oribi-completed-checkbox-title">Track
                        <span class="oribi-completed-status-on oribi-medium-font" style="display: <?php echo ( 1 == $tracking_capabilities[ 'completed' ] ? 'initial' : 'none' ); ?>">completed purchases only</span>
                        <span class="oribi-completed-status-off oribi-medium-font" style="display: <?php echo ( 0 == $tracking_capabilities[ 'completed' ] ? 'initial' : 'none' ); ?>">completed, pending, and on hold purchases</span>
                    </p>
                    <p class="oribi-switch-description">Oribi tracks by default only completed purchases and filters out any other type (pending, on hold, etc.) Disabling it will track all purchases type.</p>
                </div>
                <?php submit_button( 'Save Changes' ); ?>
                <div id="oribi-helptext">Check our <a target="_blank" href="https://oribi.io/help">help</a> section or <a href="mailto:support@oribi.io?subject=Oribi for eCommerce inquiry">contact</a> our Customer Support team for best practices.</a></div>
            </form>
        </div>
        <div id="oribi-oribi-button"><a target="_blank" href="https://oribi.io" class="button">Go to Oribi</a></div>
    </div>
    <?php
}
