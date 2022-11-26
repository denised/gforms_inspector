<?php
/**
 * Plugin Name:  Gravity Forms Inspector
 * Plugin URI:   https://github.com/denised/gforms_inspector
 * Description:  Admin tool lets you search form and field settings across multiple Gravity Forms at once.
 * Author:       Denise Draper
 * Version:      0.8
 * 
 * This is a very simple plugin you can drop into your plugin directory, or just load the php with
 * your code. 
 * 
 * It creates a page called "Gravity Form Inspector" in the WP Admin Tools that will let you look at
 * the data content of one of your forms, or search for particular form or field settings across multiple
 * forms.  For example, try searching for <pre>"cssClass"</pre> across your forms to see where you've
 * added CSS classes.
 * 
 * Current limitation: displays scalar settings only; for array-valued settings, displays only the length
 * of the array.
 * Potential future enhancement: allow the use of rgars or similar syntax to make it possible to target nested
 * settings (e.g. settings on confirmations or notifications).
 */
 

add_action('admin_menu', 'gfi_register_form_inspector_page');

function gfi_register_form_inspector_page() {
	add_submenu_page(
		'tools.php',
		'Gravity Form Inspector',
		'Gravity Form Inspector',
		'gravityforms_edit_forms',
		'forminspector',
		'gfi_form_inspector' );
}


function gfi_form_inspector() { 
    
    $fi_nonce = wp_create_nonce("fi_for_the_win!");   
    $aurl =  admin_url( "admin-ajax.php" );
    
    ?>

<h1>Look inside Gravity Forms</h1> 
    <p style="font-style: italic; font-size: 90%">[From gforms_inspector plugin]</p>
    <div class="fi-section">
    <div id="form-list" class="fi-box" style="width: 20em; flex: none;">
<?php
    foreach( GFAPI::get_forms() as $f) {
        $id = "form_{$f['id']}";
        echo "<input type='radio' id='$id' name='form_list'>";
        echo "<label for='$id'>{$f['title']}</label><br>";
    }
?>
    </div>
    <div class="fi-box fi-big-box">
        <pre id="show_the_form">
        Select a form from list.
        </pre>
    </div>
</div>
<div class="fi-section">
    <div id="field-control" class="fi-box" style="width: 20em; flex: none;">
        <p>
            <select id='fi-search-type'>
                <option value=''>Choose</option>
                <option value='form'>Form Attribute</option>
                <option value='field'>Field Attribute</option>
            </select>

            <br>
            <span id='fi-field-regex-box'>
            <label for='fi-field-regex'>Fields matching regex</label>
            <input type='text' id='fi-field-regex'><br></span>

            <label for='fi-search-value'>Attributes matching regex</label>
            <input type='text' id='fi-search-value'><br>
            <input type='button' id='fi-do-search' value="Find all Matching Attributes">
        </p>

        <p style="display: none;"> <!-- TODO: We'll do this later, if at all... -->
            <span class='warning'>Danger Zone! Form(s) export advised.</span><br>
            <label for='fi-search-replace'>Replace values with</label>
            <input type='text' id='fi-search-replace'><br>
            <input type='button' id='fi-do-replace' value="Replace All">
        </p>

        <p>Forms to search:</p>
        <div class='fi-list'>
            <input type='checkbox' id='fi-search-all'><br>
            <?php
            foreach( GFAPI::get_forms() as $f) {
                $id = "fi-search_{$f['id']}";
                echo "<input type='checkbox' id='$id'>";
                echo "<label for='$id'>{$f['title']}</label><br>";
            }
        ?>          
        </div>
    </div>
    <div id='field-search-results' class="fi-box fi-big-box">
    <p>Regex: Empty matches everything. Otherwise standard regex syntax. Examples: </p> 
            <table>
            <tr><td class='literal'>css</td><td>match anything containing the characters 'css'</td></tr>
            <tr><td class='literal'>^css</td><td>begins with 'css'</td></tr>
            <tr><td class='literal'>^css$</td><td>matches exactly the string 'css'</td></tr>
            <tr><td class='literal'>[cC]ss</td><td>try this one!</td></tr></table>
    </div>
</div>
<style>
    .fi-section {
        width: 90%;
        padding: 10px;
        display: flex;
        flex-flow: row wrap;
    }
    .fi-box {
        padding: 5px;
        outline: solid 1px;
    }
    .fi-big-box {
        overflow: scroll; 
        flex: 70%; 
        min-width: 30em; 
        max-height: 40em;
    }
    .fi-section td.literal {
        font-family: monospace;
        padding: 0 1em;
    }
</style> 
<script>
    jQuery(document).ready(
        function () {
            jQuery("#fi-field-regex-box").hide();
            jQuery('#fi-do-search').prop('disabled',true);

            jQuery("[name='form_list']").click( function(e) {
                form_num = jQuery(this).attr("id").split('_')[1]; 
                nonce = '<?php echo $fi_nonce;?>'
                url = '<?php echo $aurl;?>'
                jQuery.ajax({
                    type : "get",
                    dataType : "text",
                    url : url,
                    data : {
                        action: "fi_fetch_form", 
                        form_number: form_num, 
                        nonce: nonce
                    },
                    complete: function(response, status) {
                        if(status == "success") {
                            jQuery("#show_the_form").html(response.responseText)
                        }
                        else {
                            jQuery("#show_the_form").html("Oops<br>" + status)
                        }
                    }
                }) 
            });

            // Show hide field regex if we are looking for them
            jQuery("#fi-search-type").change( function(e) {
                cvalue = jQuery(this).val();
                if (cvalue=='field') {
                    jQuery("#fi-field-regex-box").show()
                }
                else {
                    jQuery("#fi-field-regex-box").hide()
                }
                jQuery('#fi-do-search').prop('disabled', cvalue=='')
            });

            // Make toggle checkbox work.
            jQuery('#fi-search-all').change( function(e) {
                cvalue = jQuery(this).prop("checked")
                jQuery(this).siblings().prop("checked", cvalue)
            });


            jQuery("#fi-do-search").click( function(e) {

                nonce = '<?php echo $fi_nonce;?>'
                url = '<?php echo $aurl;?>'
                search_type = jQuery('#fi-search-type').val();
                field_regex= jQuery('#fi-field-regex').val();
                search_regex = jQuery('#fi-search-value').val();
                form_list = jQuery('.fi-list').children('input:checked').toArray().map( x=>x.id.split('_')[1] )
                form_list.unshift() // drop the toggle-all box

                jQuery.ajax({
                    type : "get",
                    dataType : "text",
                    url : url,
                    data : {
                        action: "fi_search", 
                        nonce: nonce,
                        search_type: search_type,
                        field_regex: field_regex,
                        search_regex: search_regex,
                        form_list: form_list
                    },
                    complete: function(response, status) {
                        if(status == "success") {
                            jQuery("#field-search-results").html(response.responseText)
                        }
                        else {
                            jQuery("#field-search-results").html("Oops<br>" + status)
                        }
                    }
                }) 
            });
        }
    );
</script>
<?php
}


add_action( 'wp_ajax_fi_fetch_form', 'form_inspector_ajax_fetch_form' );
function form_inspector_ajax_fetch_form() {    
    if ( ! wp_verify_nonce( $_GET['nonce'], 'fi_for_the_win!' ) ) {
        die ( 'Nope, you lose.');
    }
    $formid = intval($_GET['form_number']);
    $form = GFAPI::get_form($formid);
    if ($form) {
        print_r($form);
    }
    else {
        echo "not found";
    }
    wp_die();
}

add_action( 'wp_ajax_fi_search', 'form_inspector_ajax_search_forms' );
function form_inspector_ajax_search_forms() {
    if ( ! wp_verify_nonce( $_GET['nonce'], 'fi_for_the_win!' ) ) {
        die ( 'Nope, you lose.');
    }
    $search_type = $_GET['search_type'];
    $field_regex= $_GET['field_regex'];
    $search_regex = $_GET['search_regex'];
    $form_list = $_GET['form_list'];
    //error_log( print_r($_GET, true));

    if ( $search_type != 'form' && $search_type != 'field' ) {
        echo "<p>You need to set the search type</p>";
    }
    else if ( !count($form_list) ) {
        echo "<p>You need to select at least some form(s)";
    }
    else {
        // Add delimiters (required by preg_match);
        if ($field_regex) {
            $field_regex = '/' . $field_regex . '/';
        }
        if ($search_regex) {
            $search_regex = '/' . $search_regex . '/';
        }


        if ($search_type == 'form') {
            echo "<table><tr><th> Form </th><th> Attribute </th><th> Value </th></tr>";
        }
        else {
            echo "<table><tr><th> Form </th><th> Field </th><th> Attribute </th><th> Value </th></tr>";            
        }


        foreach ($form_list as $fid) {
            $form = GFAPI::get_form($fid);
            if ( ! $form ) continue;
            
            if ($search_type == 'form' ) { // search form attributes
                $first = true;
                foreach( $form as $k=>$v ) {
                    $show = (!$search_regex || preg_match($search_regex, $k));
                    if ($show) {
                        $formcol = ($first ? $form['title'] : "");
                        if (is_array($v)) {
                            $cnt = count($v);
                            $v = "Array($cnt)";
                        }
                        echo "<tr><td>$formcol</td><td>$k</td><td>$v</td></tr>";
                        $first = false;
                    }
                }
                if ($first) { // we never output anything, let's tell the user...
                    echo "<tr><td>{$form['title']}</td><td rowspan='2'>No attributes matched.</td></tr>";
                }
            }
            else { // search field attributes
                $first = true;
                $fields = $form['fields'];
                foreach( $fields as $field ) {
                    $showfield = (!$field_regex || preg_match($field_regex, $field['label']));
                    if ($showfield) {
                        foreach( $field as $k=>$v ) {
                            $show = (!$search_regex || preg_match($search_regex, $k));
                            if ($show) {
                                $formcol = ($first ? $form['title'] : "");
                                if (is_array($v)) {
                                    $cnt = count($v);
                                    $v = "Array($cnt)";
                                }
                                echo "<tr><td>$formcol</td><td>{$field['label']}<td>$k</td><td>$v</td></tr>";
                                $first = false;
                            }                            
                        }
                    }
                }
                if ($first) { // we never output anything, let's tell the user...
                    echo "<tr><td>{$form['title']}</td><td colspan='3'>Either no fields matched or no attributes matched</td></tr>";
                }
            }
        }

        echo "</table>";
    }

    wp_die();
}
