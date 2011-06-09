<?php
/*
Plugin name: Linked Pages
Plugin uri: http://athena.outer-reaches.com/wiki/doku.php?id=projects:wplp:home
Description: Displays links to posts which reference the current post using a custom field and provides customisable page picker meta boxes for the editors allowing you to create the links between posts.  For some detailed instructions about it's use, check out my <a href="http://athena.outer-reaches.com/wiki/doku.php?id=projects:wplp:home">wiki</a>.  To report bugs or make feature requests, visit the Outer Reaches Studios <a href="http://mantis.outer-reaches.co.uk">issue tracker</a>, you will need to signup an account to report issues.
Author: Christina Louise Warne
Author uri: http://athena.outer-reaches.com/
Version: 0.2.3
*/

/*
LINKED PAGES WIDGET
by Christina Louise Warne (aka AthenaOfDelphi), http://athena.outer-reaches.com/
from The Outer Reaches, http://www.outer-reaches.com/

LINKED PAGES WIDGET is free software: you can redistribute it
and/or modify it under the terms of the GNU General Public License as
published by the Free Software Foundation, either version 3 of
the License, or (at your option) any later version.

LINKED PAGES WIDGET is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty
of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the GNU General Public License for details.

You should have received a copy of the GNU General Public License
along with LINKED PAGES WIDGET.
If not, see www.gnu.org/licenses/
*/

include_once("linkedpages-constants.php");

//-------------------------------------------------------------------------------------------------------------------------------------------
// Page Linker Meta Box
//
// This section of the widget contains the code for the page linking meta box system
//-------------------------------------------------------------------------------------------------------------------------------------------

function lp_display_meta_box($post,$boxargs) {
    global $wpdb;
    
    $boxes = get_option( LPLINKEROPTIONS );
    
    if ( $boxes ) {
        $boxindex = $boxargs['args']['boxindex'];
        
        if ( array_key_exists( $boxindex, $boxes ) ) {
            $boxrec = $boxes[$boxindex];
            
            $data = get_post_meta( $post->ID, $boxrec['field'], true );
            
            $query = "SELECT id,post_title FROM $wpdb->posts WHERE (post_type='".$boxrec['linkposttype']."') AND (post_status='publish')";
            switch ($boxrec['sortorder']) {
            case 0 :
                break;
            case 1 :
                $query = $query." ORDER BY id";
                break;
            case 2 :
                $query = $query." ORDER BY id DESC";
                break;
            case 3 :
                $query = $query." ORDER BY post_title";
                break;
            case 4 :
                $query = $query." ORDER BY post_title DESC";
                break;
            case 5 :
                $query = $query." ORDER BY post_date_gmt";
                break;
            case 6 :
                $query = $query." ORDER BY post_date_gmt DESC";
                break;
            }
            
            $items = $wpdb->get_results( $query );
            
            if ($items) {
                // We have some data
                $noncename = "lp-picker-".$post->ID."-".$boxindex;
                $nonce = wp_create_nonce( $noncename );
                echo '<input type="hidden" name="'.$noncename.'" value="'.$nonce.'" />';
                if ( $boxrec['comment'] != "" ) {
                    echo '<p>'.$boxrec['comment'].'</p>';
                }
                echo '<select class="widefat" name="lp-picker-'.$boxindex.'" id="lp-picker-'.$boxindex.'">';
                echo '<option value="0"'.($data==""?" selected":"").'>Select the required target page...</option>';
                foreach ($items as $item) {
                    echo '<option value="'.$item->id.'"'.($data==$item->id?" selected":"").'>'.$item->post_title.'</option>';
                }
                echo '</select>';
            } else {
                echo "<p>".sprintf( __( 'No suitable target pages were found for this link type, add pages/posts of type %1$s to enable linking!', $boxrec['linkposttype'] ) )."</p>";
            }            
        }
    }
}

//-------------------------------------------------------------------------------------------------------------------------------------------

function lp_create_meta_boxes() {
    if ( function_exists('add_meta_box') ) {
        $boxes = get_option( LPLINKEROPTIONS );
    
        if ( $boxes ) {
            foreach ($boxes as $index => $boxrec) {
                add_meta_box( 'lp-picker-'.$index, $boxrec['title'], 'lp_display_meta_box', $boxrec['posttype'], 'normal', 'high', array( 'boxindex' => $index ) );
            }
        }
    }
}

//-------------------------------------------------------------------------------------------------------------------------------------------

function lp_save_meta_boxes( $post_id ) {
    $boxes = get_option( LPLINKEROPTIONS );

    $post = get_post( $post_id );
    if ( $post ) {
        if ( $post->post_type == 'revision' ) {
            $post_id = $post->post_parent;
        }
    }
    
    if ( $boxes ) {    
        foreach ($boxes as $index => $boxrec) {
            $noncename = "lp-picker-".$post_id."-".$index;
            
            if ( !wp_verify_nonce( $_POST[ $noncename ], $noncename ) ) {
                return;
            }
        
            if ( current_user_can( 'edit_page', $post_id ) || current_user_can( 'edit_post', $post_id ) ) {
                $data = $_POST['lp-picker-'.$index];

                if ( $data == 0 ) {
                    delete_post_meta( $post_id, $boxrec['field'] );
                } else {
                    if ( get_post_meta( $post_id, $boxrec['field'] ) == "") {
                        add_post_meta( $post_id, $boxrec['field'], $data, true);
                    } else {
                        update_post_meta( $post_id, $boxrec['field'], $data);
                    }
                }
            } else {
                return;
            }
        }
    }
}

//-------------------------------------------------------------------------------------------------------------------------------------------

function lp_fields_there( $suffix ) {
    $allthere=true;
    
    if (!isset($_POST['lppfield-'.$suffix]) ) {
        $allthere = false;
    } else {
        if ($_POST['lppfield-'.$suffix]=="") {
            $allthere = false;
        }
    }
    if (!isset($_POST['lppposttype-'.$suffix]) ) { 
        $allthere = false;
    } else {
        if ($_POST['lppposttype-'.$suffix]=="") {
            $allthere = false;
        }
    }
    if (!isset($_POST['lpplinkposttype-'.$suffix]) ) {
        $allthere = false;
    } else {
        if ($_POST['lpplinkposttype-'.$suffix]=="") {
            $allthere = false;
        }
    }
    if (!isset($_POST['lpptitle-'.$suffix]) ) {
        $allthere = false;
    } else {
        if ($_POST['lpptitle-'.$suffix]=="") {
            $allthere = false;
        }
    }
    
    return $allthere;
}

//-------------------------------------------------------------------------------------------------------------------------------------------

function lp_config_array_from_post( $suffix ) {
    $data = array();
    
    $data['field']=$_POST['lppfield-'.$suffix];    
    $data['posttype']=$_POST['lppposttype-'.$suffix];    
    $data['linkposttype']=$_POST['lpplinkposttype-'.$suffix];    
    $data['title']=$_POST['lpptitle-'.$suffix];    
    if (isset($_POST['lppcomment-'.$suffix])) {
        $data['comment']=$_POST['lppcomment-'.$suffix];
    }
    $data['sortorder']=$_POST['lppsortorder-'.$suffix];
    
    return $data;
}

//-------------------------------------------------------------------------------------------------------------------------------------------

function lp_save_config( $config ) {
    delete_option( LPLINKEROPTIONS );
    add_option( LPLINKEROPTIONS, $config );
}

//-------------------------------------------------------------------------------------------------------------------------------------------

function lp_unset_post( $suffix ) {
    unset($_POST['lppfield-'.$suffix]);
    unset($_POST['lppposttype-'.$suffix]);
    unset($_POST['lpplinkposttype-'.$suffix]);
    unset($_POST['lpptitle-'.$suffix]);    
    unset($_POST['lppcomment-'.$suffix]);
    unset($_POST['lppsortorder-'.$suffix]);
}

//-------------------------------------------------------------------------------------------------------------------------------------------

function lp_new_id( $config ) {
    if ( $config ) {
        $newid = count( $config );
        while ( array_key_exists( $newid, $config ) ) {
            $newid++;
        }
        return $newid;
    } else {
        return 1;
    }
}

//-------------------------------------------------------------------------------------------------------------------------------------------

function lp_add_message( $newmsg, &$dstmsg ) {
    if ( $dstmsg != "" ) {
        $dstmsg .= "<br />\n";
    }
    $dstmsg .= $newmsg;
}

//-------------------------------------------------------------------------------------------------------------------------------------------

function lp_sort_order_options( $sortorder ) {
    ?>
    <option value="0"<?php if ( $sortorder == 0 ) { echo " selected"; } ?>>As it comes</option>
    <option value="1"<?php if ( $sortorder == 1 ) { echo " selected"; } ?>>By ID (1..10)</option>
    <option value="2"<?php if ( $sortorder == 2 ) { echo " selected"; } ?>>By ID (10..1)</option>
    <option value="3"<?php if ( $sortorder == 3 ) { echo " selected"; } ?>>By Title (A..Z)</option>
    <option value="4"<?php if ( $sortorder == 4 ) { echo " selected"; } ?>>By Title (Z..A)</option>
    <option value="5"<?php if ( $sortorder == 5 ) { echo " selected"; } ?>>By Time (Oldest first)</option>
    <option value="6"<?php if ( $sortorder == 6 ) { echo " selected"; } ?>>By Time (Youngest first)</option>
    <?php
}

//-------------------------------------------------------------------------------------------------------------------------------------------

function lp_cp_table_headers() {
    ?>
    <thead>
        <tr>
            <th align="center" width="12%" class="manage-column" scope="col"><?php _e( 'Picker ID', LPTEXTDOMAIN ); ?></th>
            <th align="center" width="12%" class="manage-column" scope="col"><?php _e( 'Title', LPTEXTDOMAIN ); ?></th>
            <th align="center" width="12%" class="manage-column" scope="col"><?php _e( 'Custom Field', LPTEXTDOMAIN ); ?></th>
            <th align="center" width="12%" class="manage-column" scope="col"><?php _e( 'Picker On Post Type', LPTEXTDOMAIN ); ?></th>
            <th align="center" width="12%" class="manage-column" scope="col"><?php _e( 'Link To Post Type', LPTEXTDOMAIN ); ?></th>
            <th align="center" width="15$" class="manage-column" scope="col"><?php _e( 'Sort Order', LPTEXTDOMAIN ); ?></th>
            <th align="center" width="25%" class="manage-column" scope="col"><?php _e( 'Comment', LPTEXTDOMAIN ); ?></th>
        </tr>
    </thead>
    <tfoot>
        <tr>
            <th align="center" width="12%" class="manage-column" scope="col"><?php _e( 'Picker ID', LPTEXTDOMAIN ); ?></th>
            <th align="center" width="12%" class="manage-column" scope="col"><?php _e( 'Title', LPTEXTDOMAIN ); ?></th>
            <th align="center" width="12%" class="manage-column" scope="col"><?php _e( 'Custom Field', LPTEXTDOMAIN ); ?></th>
            <th align="center" width="12%" class="manage-column" scope="col"><?php _e( 'Picker On Post Type', LPTEXTDOMAIN ); ?></th>
            <th align="center" width="12%" class="manage-column" scope="col"><?php _e( 'Link To Post Type', LPTEXTDOMAIN ); ?></th>
            <th align="center" width="15$" class="manage-column" scope="col"><?php _e( 'Sort Order', LPTEXTDOMAIN ); ?></th>
            <th align="center" width="25%" class="manage-column" scope="col"><?php _e( 'Comment', LPTEXTDOMAIN ); ?></th>
        </tr>
    </tfoot>
    <?php
}

//-------------------------------------------------------------------------------------------------------------------------------------------

function lp_pickers() {
    // Check that the current user can manage the options... if not, get rid of them
    if (!current_user_can('manage_options'))  {
        wp_die( __('You do not have sufficient permissions to access this page.') );
    }
  
    $boxes = get_option( LPLINKEROPTIONS );
    
    // Reset the feedback message
    $successmessage = "";
    $errormessage = "";
    $blankpost = true;
    
    if ( wp_verify_nonce( $_POST[ 'lp-picker-options' ], 'lp-picker-options' ) ) {
        
        // Process the incoming request
        if ( ( isset( $_POST[ 'lpnewadd' ] ) ) && ( $_POST[ 'lpnewadd' ] == __( "Add", VSTEXTDOMAIN ) ) ) {
            
            //------------------------------------------------------------------
            // Add new picker
                        
            $newindex = lp_new_id( $boxes );
            
            // Verify we have all our information
            // field linkposttype posttype comment* title
            if ( !lp_fields_there( 'new' ) ) {
                $errormessage = __( "New page picker sidebar was not added - You must specify a custom field name, the post type on which to use the picker, the post type which will provide the list of pages and the title for the meta box!", LPTEXTDOMAIN );
                $blankpost = false;
            }    

            // If we've gotten to this point with no message, then we're done and we
            // can save the configuration etc.
            if ( $errormessage == "" ) {
                
                $boxconfig = lp_config_array_from_post( 'new' );
                $boxes[ $newindex ] = $boxconfig;
                    
                lp_save_config ( $boxes );
                
                // Set the feedback message
                $successmessage = __( "New page picker created", LPTEXTDOMAIN );
            }
            
        } else {
            if ( isset( $_POST[ 'lpdel' ] ) ) {
                //--------------------------------------------------------------
                // Delete a sidebar
                
                $id = $_POST[ 'lpdel' ];
                
                if ( array_key_exists( $id, $boxes ) ) {
                    unset( $boxes[ $id ] );
                    
                    $successmessage = sprintf( __( "Page picker %s deleted", LPTEXTDOMAIN ), $id );
                    
                    lp_save_config( $boxes );
                } else {
                    $errormessage = sprintf( __( "Deletion of page picker %s failed - Page picker does not exist!", LPTEXTDOMAIN ), $id );
                }
            } else {
                if ( isset( $_POST[ 'lpupd' ] ) ) {
                    //----------------------------------------------------------
                    // Update the existing sidebars
                    
                    if ( $boxes ) {
                        foreach ( $boxes as $lpid => $boxrec ) {
                            // For each existing bar, collect a new config array for it
                            
                            if ( lp_fields_there( $lpid ) ) {
                                $boxconfig = lp_config_array_from_post( $lpid );
                            
                                // Check the whole record for changes
                                $changed = false;
                                foreach ( $boxconfig as $key => $val ) {
                                    if ( $boxrec[ $key ] != $val ) {
                                        $changed = true;
                                        break;
                                    }
                                }
                                
                                // If it has changed, then save it
                                if ( $changed ) {
                                    // Remove the existing configuration
                                    unset( $config[ $lpid ] );
                                
                                    // Store the new one
                                    $boxes[ $lpid ] = $boxconfig;
                                
                                    lp_add_message( sprintf( __( "Picker %s updated successfully", LPTEXTDOMAIN ), $lpid ), $successmessage );
                                }
                            } else {
                                lp_add_message( sprintf( __( "Changes to picker %s not saved - You must specify a custom field name, the post type on which to use the picker, the post type which will provide the list of pages and the title for the meta box!", LPTEXTDOMAIN ), $lpid ), $errormessage );
                            }
                        }
                    } else {
                        lp_add_message( __( "To add a picker, use the form at the bottom of the page and click the 'Add' button in the picker ID", LPTEXTDOMAIN ), $errormessage );
                    }
                    
                    // Save the revised configuration
                    lp_save_config( $boxes );
                } else {
                    $errormessage = __( "Unknown action!", LPTEXTDOMAIN );
                }
            }
        }
    } else {
        if ($_SERVER['REQUEST_METHOD']=='POST') {
            $errormessage = __( 'The security token expired, please try again!', LPTEXTDOMAIN );
        }
    }
    
    // Set out form verification value
    $nonce=wp_create_nonce( 'lp-picker-options' );
    
    if ( $blankpost ) {
        // Blank the post variables ready for a new form
        lp_unset_post('new');                
    }

    ?>
    <div class="wrap">
    <h2><?php _e( 'Linked Page Pickers', LPTEXTDOMAIN ) ?> (<?php printf( __( 'Version. %1$s', LPTEXTDOMAIN ), LPVERSION ) ?>)</h2>
    <?php 
    // Handle the messages that can be returned by the request processing
    $includehr = false;
    if ( ( isset( $successmessage ) ) && ( $successmessage != "" ) ) {
        echo '<div class="updated"><p><strong>' . $successmessage . '</strong></p></div>';
        $includehr = true;
    } 
    if ( ( isset( $errormessage ) ) && ( $errormessage != "" ) ) {
        echo '<div class="error"><p><strong>' . $errormessage . '</strong></p></div>';
        $includehr = true;
    } 
    if ( $includehr ) {
        echo '<hr />';
    }
    ?>
    <p><?php _e( 'Using Linked Page Pickers, you can add page pickers to the post editor allowing you to store a link from one page to another using custom fields which can then be displayed with the Linked Pages widget.', LPTEXTDOMAIN ) ?></p>
    <hr />
    <form id="lpp-form" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
    <input type="hidden" name="lp-picker-options" value="<?php echo $nonce; ?>" />
    <div class="tablenav"><div class="alignright"><input type="submit" class="button-secondary action" name="lpupd" value="<?php _e( "Save", LPTEXTDOMAIN ); ?>" /></div></div>
    <table class="widefat">
    <?php lp_cp_table_headers(); ?>
    <tbody>    
    <?php
    // Build the list of sidebars    
    // Add 'alternate' class to TR to get alternating row backgrounds
    $row=0;
    
    if ( $boxes && count( $boxes ) > 0 ) {
        foreach ( $boxes as $boxid => $boxrec ) {
            ?>
    <tr<?php if ( $row % 2 == 0 ) { echo ' class="alternate"'; } ?>>
        <td align="center" style="vertical-align:middle">
            <span class="submit"><input title="<?php _e( "Delete this picker", LPTEXTDOMAIN ); ?>" onclick="return confirm('<?php _e( 'Are you sure you want to delete this picker?', LPTEXTDOMAIN ); ?>');" type="submit" name="lpdel" value="<?php echo $boxid; ?>" /></span></td>
        <td align="center" style="vertical-align:middle">
            <input type="text" name="lpptitle-<?php echo $boxid; ?>" style="width:100%" value="<?php echo $boxrec[ "title" ]; ?>" />
        </td>
        <td align="center" style="vertical-align:middle">
            <input type="text" name="lppfield-<?php echo $boxid; ?>" style="width:100%" value="<?php echo $boxrec[ "field" ]; ?>" />
        </td>
        <td align="center" style="vertical-align:middle">
            <input type="text" name="lppposttype-<?php echo $boxid; ?>" style="width:100%" value="<?php echo $boxrec[ "posttype" ]; ?>" />
        </td>
        <td align="center" style="vertical-align:middle">
            <input type="text" name="lpplinkposttype-<?php echo $boxid; ?>" style="width:100%" value="<?php echo $boxrec[ "linkposttype" ]; ?>" />
        </td>
        <td align="center" style="vertical-align:middle">
            <select name="lppsortorder-<?php echo $boxid; ?>" style="width:100%">
                <?php lp_sort_order_options( $boxrec[ "sortorder" ] ); ?>
            </select>
        </td>
        <td align="center" style="vertical-align:middle">
            <textarea name="lppcomment-<?php echo $boxid; ?>" style="width:100%" rows="3" cols="25"><?php echo $boxrec[ "comment" ]; ?></textarea>
        </td>
    </tr>
            <?php    
            $row=(($row+1)%2);
        }
    } else {
        ?>
        <tr class="alternate"><td colspan="7" align="center"><?php _e( "No page pickers", LPTEXTDOMAIN ); ?></td></tr>
        <?php
    }
    ?>
    </table>
    <div class="tablenav"><div class="alignright"><input type="submit" class="button-secondary action" name="lpupd" value="<?php _e( "Save", LPTEXTDOMAIN ); ?>" /></div></div>
    <h2>Add new page picker</h2>    
    <table class="widefat">
    <?php lp_cp_table_headers(); ?>
    <tbody>    
    <tr class="alternate">
        <td align="center" style="vertical-align:middle"><span class="submit"><input type="submit" name="lpnewadd" value="<?php _e( "Add", LPTEXTDOMAIN ); ?>" /></span></td>
        <td align="center" style="vertical-align:middle">
            <input type="text" name="lpptitle-new" style="width:100%" value="<?php echo $_POST[ "lpptitle-new" ]; ?>" />
        </td>
        <td align="center" style="vertical-align:middle">
            <input type="text" name="lppfield-new" style="width:100%" value="<?php echo $_POST[ "lppfield-new" ]; ?>" />
        </td>
        <td align="center" style="vertical-align:middle">
            <input type="text" name="lppposttype-new" style="width:100%" value="<?php echo $_POST[ "lppposttype-new" ]; ?>" />
        </td>
        <td align="center" style="vertical-align:middle">
            <input type="text" name="lpplinkposttype-new" style="width:100%" value="<?php echo $_POST[ "lpplinkposttype-new" ]; ?>" />
        </td>
        <td align="center" style="vertical-align:middle">
            <select name="lppsortorder-new" style="width:100%">
                <?php lp_sort_order_options( $_POST[ "lppsortorder-new" ] ); ?>
            </select>
        </td>
        <td align="center" style="vertical-align:middle">
            <textarea name="lppcomment-new" style="width:100%" rows="3" cols="25"><?php echo $_POST[ "lppcomment-new" ]; ?></textarea>
        </td>
    </tr>
    </tbody>
    </table>
    </form>
    <hr />
    <p><?php printf( __( 'For assistance with Linked Pages, post your comments on <a href="%1$s" target="_BLANK">my blog</a>, and to read the on-line user manual visit <a href="%2$s" target="_BLANK">my wiki</a>.  <i>Thanks, AthenaOfDelphi</i>', LPTEXTDOMAIN ), LPBLOGLINK, LPWIKILINK ) ?></p>
    <p><?php printf( __( 'Linked Pages is copyright &copy; 2011 Christina Louise Warne (aka <a href="%1$s" target="_BLANK">AthenaOfDelphi</a>)', LPTEXTDOMAIN), LPSITELINK ) ?></p>
    </div>
    <?php
}

//-------------------------------------------------------------------------------------------------------------------------------------------

function lp_linker_menu() {
    add_submenu_page( 'plugins.php', __( 'Linked Page Pickers', LPTEXTDOMAIN ), __( 'Page Pickers', LPTEXTDOMAIN ), 'manage_options', 'linked-page-pickers', 'lp_pickers' );
}

//-------------------------------------------------------------------------------------------------------------------------------------------

add_action('admin_menu', 'lp_linker_menu');
add_action('admin_menu', 'lp_create_meta_boxes');
add_action('save_post', 'lp_save_meta_boxes');
  
//-------------------------------------------------------------------------------------------------------------------------------------------
// Linked Pages Widget Routines
//
// This section of the plugin contains the code for the linked pages display widget
//-------------------------------------------------------------------------------------------------------------------------------------------

// Run the provided data through the required filters
//function lp_filterfield( $dontfilter, $dontconvert, &$data, $doreplace = false )
//{
//    if ( !$dontconvert ) {
//        // Convert chars
//        $data = apply_filters( 'linked_pages_value1', $data );
//    }
//    
//    // Strip slashes
//    $data = apply_filters( 'linked_pages_value2', $data );
//    
//    if ( !$dontfilter ) {
//        // WP Texturize
//        $data = apply_filters( 'linked_pages_value3', $data );
//    }
//    $data = addslashes( $data );
//    
//    if ( $doreplace ) {
//        $data = str_replace( chr(13).chr(10), chr(10), $data );
//        $data = str_replace( chr(10).chr(13), chr(10), $data );
//        $data = str_replace( chr(10), '\n', $data );
//    }
//}

//-------------------------------------------------------------------------------------------------------------------------------------------

function wp_widget_linked_pages_make_link(&$links,$link,$linkbreak,$pageurl,$pagetitle) {
    if ($links!='') {
        $links=$links.$linkbreak;
    }
    $links=$links.sprintf($link,$pageurl,$pagetitle);    
}

// Widget Display Function for the Advanced Custom Field Widget
function wp_widget_linked_pages( $args, $widget_args = 1 ) {
    // Get hold of the global WP database object
    global $wpdb;
    
    // Let's begin our widget.
    extract( $args, EXTR_SKIP );
    
    // Our widgets are stored with a numeric ID, process them as such
    if ( is_numeric($widget_args) )
        $widget_args = array( 'number' => $widget_args );
    
    // We'll need to get our widget data by offsetting for the default widget
    $widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
    
    // Offset for this widget
    extract( $widget_args, EXTR_SKIP );
    
    // We'll get the options and then specific options for our widget further below
    $options = get_option( LPOPTIONS );
    
    // If we don't have the widget by its ID, then what are we doing?
    if ( ! isset( $options[$number] ) ) {
        return;
    }
    
    $field      = $options[$number]['field'];
    $title      = apply_filters( 'widget_text', $options[$number]['title'] );
    $body       = apply_filters( 'widget_text', $options[$number]['body'] );
    $pretitle   = apply_filters( 'widget_text', $options[$number]['pretitle'] );
    $posttitle  = apply_filters( 'widget_text', $options[$number]['posttitle'] );
    $link       = $options[$number]['link'];
    $linkbreak  = apply_filters( 'widget_text', $options[$number]['linkbreak'] );
    $prelinks   = apply_filters( 'widget_text', $options[$number]['prelinks'] );
    $postlinks  = apply_filters( 'widget_text', $options[$number]['postlinks'] );
    $prewidget  = apply_filters( 'widget_text', $options[$number]['prewidget'] );
    $postwidget = apply_filters( 'widget_text', $options[$number]['postwidget'] );
    
    // Version 0.2.3.
    if ($options['version']>=1) {
        $searchforsimilar   = ( $options[$number]['searchforsimilar'] );
        $similarlink        = ($options[$number]['similarlink']!=""?$options[$number]['similarlink']:$link);
        $similarmode        = (int)$options[$number]['similarlinkmode'];
    } else {
        $seachforsimilar = false;
        $similarlink = '';
        $similarmode = 0;
    }
    
    global $wp;
    $originalquerystring = $wp->query_string;
    $originalqueryvars = $wp->query_vars;
    
    $wp->build_query_string();
    $realpostlist = new WP_Query();
    $realpostlist->query( $wp->query_vars );
    
    $wp->query_string = $originalquerystring;
    $wp->query_vars = $originalqueryvars;
    
    if ( $realpostlist->post_count == 0 ) {
        return;
    }
    
    $ourpost = $realpostlist->post;
    
    if ($realpostlist->is_single()||$realpostlist->is_page()) {
        $targetid = $ourpost->ID;
        $targettitle = $ourpost->post_title;

        if ($searchforsimilar) {
            $ourfielddata = get_post_meta( $targetid, $field, true );
            
            if ( $ourfielddata ) {
                $targetid = $ourfielddata;
                
                $parentpost = get_post($targetid);
                if ($parentpost) {
                    $targettitle = $parentpost->post_title;
                }
            }
        }
        
        $linkedpages = $wpdb->get_results(
            "SELECT
                p.id
            FROM
                $wpdb->postmeta m,
                $wpdb->posts p
            WHERE
                (p.post_status='publish') AND
                (p.id=m.post_id) AND
                (m.meta_key='$field') AND
                (m.meta_value='$targetid')");
                
        if ($linkedpages) {
            
            $links = '';
            
            foreach ( $linkedpages as $lprec ) {
                $pageid = $lprec->id;
                $pagedata = get_post( $pageid );
                $pageurl = get_permalink( $pageid );
                $pagetitle = $pagedata->post_title;
                
                $dolink = true;
                
                if ($searchforsimilar) {
                    if ($pageid==$ourpost->ID) {
                        $dolink=false;
                        
                        switch ($similarmode) {
                            case 0 :
                                wp_widget_linked_pages_make_link($links,$similarlink,$linkbreak,$pageurl,$pagetitle);
                                break;
                            case 1 :
                                // Do nothing
                                break;
                        }
                    }
                }
                
                if ($dolink) {
                    wp_widget_linked_pages_make_link($links,$link,$linkbreak,$pageurl,$pagetitle);
                }
            }
            
            if ($links!='') {
                echo $prewidget;
                if ( $title ) {
                    echo "\n$pretitle";
                    echo sprintf( $title, $targetid, $targettitle );
                    echo "$posttitle\n";
                }
                if ( $body ) {
                    echo sprintf( $body, $targetid, $targettitle );
                }
                echo $prelinks;
                echo $links;
                echo $postlinks;
                echo $postwidget;
            }
        }
    }
}

//-------------------------------------------------------------------------------------------------------------------------------------------

// Function for the Advanced Custom Field Widget options panels
function wp_widget_linked_pages_control( $widget_args ) {
    // Establishes what widgets are registered, i.e., in use
    global $wp_registered_widgets;
   
    // We shouldn't update, i.e., process $_POST, if we haven't updated
    static $updated = false;
    // Our widgets are stored with a numeric ID, process them as such
    if ( is_numeric( $widget_args ) )
        $widget_args = array( 'number' => $widget_args );
    // We can process the data by numeric ID, offsetting for the '1' default
    $widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
    // Complete the offset with the widget data
    extract( $widget_args, EXTR_SKIP );
    // Get our widget options from the databse
    $options = get_option( LPOPTIONS );
    
    //if ( isset( $options['version'] ) ) {
    //    if ( (int)$options['version']<LPCURRENTCONFIGVERSION ) {
    //        wp_widget_linked_pages_upgrade_config($options);
    //    }
    //}
    
    // If our array isn't empty, process the options as an array
    if ( !is_array( $options ) )
        $options = array();
        
    // If we haven't updated (a global variable) and there's no $_POST data, no need to run this
    if ( !$updated && ! empty( $_POST['sidebar'] ) ) {
        // If this is $_POST data submitted for a sidebar
        $sidebar = (string) $_POST['sidebar'];
        // Let's konw which sidebar we're dealing with so we know if that sidebar has our widget
        $sidebars_widgets = wp_get_sidebars_widgets();
        // Now we'll find its contents
        if ( isset( $sidebars_widgets[$sidebar] ) ) {
            $this_sidebar =& $sidebars_widgets[$sidebar];
        } else {
            $this_sidebar = array();
        }
        // We must store each widget by ID in the sidebar where it was saved
        foreach ( $this_sidebar as $_widget_id ) {
            // Process options only if from a Widgets submenu $_POST
            if ( 'wp_widget_linked_pages' == $wp_registered_widgets[$_widget_id]['callback'] && isset( $wp_registered_widgets[$_widget_id]['params'][0]['number'] ) ) {
                // Set the array for the widget ID/options
                $widget_number = $wp_registered_widgets[$_widget_id]['params'][0]['number'];
                // If we have submitted empty data, don't store it in an array.
                if ( !in_array( "linked-pages-$widget_number", $_POST['widget-id'] ) )
                    unset( $options[$widget_number] );
            }
        }
        
        // If we are returning data via $_POST for updated widget options, save for each widget by widget ID
        foreach ( (array) $_POST['widget-linked-pages'] as $widget_number => $widget_linked_pages ) {
            
            if ( !isset( $widget_linked_pages['field'] ) && isset( $options[$widget_number] ) ) {
                continue;
            }
                
            $field = strip_tags( stripslashes( $widget_linked_pages['field'] ) );
            $title = strip_tags( stripslashes( $widget_linked_pages['title'] ) );
            $searchforsimilar = ( $widget_linked_pages['searchforsimilar'] != '' );
            $similarlinkmode = (int)$widget_linked_pages['similarlinkmode'];
            
            // For the optional text, let's carefully process submitted data
            if ( current_user_can( 'unfiltered_html' ) ) {
                $body = stripslashes( $widget_linked_pages['body'] );
                $pretitle = stripslashes( $widget_linked_pages['pretitle'] );
                $posttitle = stripslashes( $widget_linked_pages['posttitle'] );
                $link = stripslashes( $widget_linked_pages['link'] );
                $linkbreak = stripslashes( $widget_linked_pages['linkbreak'] );
                $prelinks = stripslashes( $widget_linked_pages['prelinks'] );
                $postlinks = stripslashes( $widget_linked_pages['postlinks'] );
                $prewidget = stripslashes( $widget_linked_pages['prewidget'] );
                $postwidget = stripslashes( $widget_linked_pages['postwidget'] );
                $similarlink = stripslashes( $widget_linked_pages['similarlink'] );
                
            } else {
                $body = stripslashes( wp_filter_post_kses( $widget_linked_pages['body'] ) );
                $pretitle = stripslashes( wp_filter_post_kses( $widget_linked_pages['pretitle'] ) );
                $posttitle = stripslashes( wp_filter_post_kses( $widget_linked_pages['posttitle'] ) );
                $link = stripslashes( wp_filter_post_kses( $widget_linked_pages['link'] ) );
                $linkbreak = stripslashes( wp_filter_post_kses( $widget_linked_pages['linkbreak'] ) );
                $prelinks = stripslashes( wp_filter_post_kses( $widget_linked_pages['prelinks'] ) );
                $postlinks = stripslashes( wp_filter_post_kses( $widget_linked_pages['postlinks'] ) );
                $prewidget = stripslashes( wp_filter_post_kses( $widget_linked_pages['prewidget'] ) );
                $postwidget = stripslashes( wp_filter_post_kses( $widget_linked_pages['postwidget'] ) );
                $similarlink = stripslashes( wp_filter_post_kses( $widget_linked_pages['similarlink'] ) );
                
            }
            
            // We're saving as an array, so save the options as such
            $options[$widget_number] = compact( 
                'field','title','body','pretitle','posttitle','link','linkbreak','prelinks','postlinks','prewidget','postwidget',
                // version 0.2.3
                'searchforsimilar','similarlinkmode','similarlink'
            );
        }
        
        $options['version'] = LPCURRENTCONFIGVERSION;
        
        // Update our options in the database
        update_option( LPOPTIONS, $options );
        // Now we have updated, let's set the variable to show the 'Saved' message
        $updated = true;
    }
    
    // Variables to return options in widget menu below
    if ( -1 == $number ) {
        $field              = '';
        $title              = '';
        $body               = '';
        $pretitle           = esc_attr('<h3 class="widgettitle">');
        $posttitle          = esc_attr('</h3>');
        $link               = esc_attr('<li><a href="%1$s">%2$s</a></li>');
        $linkbreak          = '';
        $prelinks           = esc_attr('<ul>');
        $postlinks          = esc_attr('</ul>');
        $prewidget          = esc_attr('<li>');
        $postwidget         = esc_attr('</li>');
        $number             = '%i%';
        
        // version 0.2.3
        $searchforsimilar   = false;
        $similarlinkmode    = 0;  // Default (A link)
        $similarlink        = esc_attr('<li class="lp-current-page"><a href="%1$s">%2$s</a></li>');
    } else {
        $field              = esc_attr( $options[$number]['field'] );;
        $title              = esc_attr( $options[$number]['title'] );
        $body               = esc_attr( $options[$number]['body'] );
        $pretitle           = esc_attr( $options[$number]['pretitle'] );
        $posttitle          = esc_attr( $options[$number]['posttitle'] );
        $link               = esc_attr( $options[$number]['link'] );
        $linkbreak          = esc_attr( $options[$number]['linkbreak'] );
        $prelinks           = esc_attr( $options[$number]['prelinks'] );
        $postlinks          = esc_attr( $options[$number]['postlinks'] );
        $prewidget          = esc_attr( $options[$number]['prewidget'] );
        $postwidget         = esc_attr( $options[$number]['postwidget'] );
        
        // Version 0.2.3
        if (isset($options[$number]['similarlinkmode'])) {
            $searchforsimilar   = ( $options[$number]['searchforsimilar'] );
            $similarlinkmode    = (int)$options[$number]['similarlinkmode']; // 0 = Link (uses own format), 1 = Text only, 2 = Remove
            $similarlink        = esc_attr( $options[$number]['similarlink'] );
        } else {
            $searchforsimilar   = false;
            $similarlinkmode    = 0;
            $similarlink        = esc_attr('<li class="lp-current-page"><a href="%1$s">%2$s</a></li>');
        }
    }
    
    // Generate the widget control panel
    ?>
    <div style="width:700px">
    <p><?php _e( 'Linked Pages instance ID (use this with the theme function):', LPTEXTDOMAIN ); ?> <b></i><?php if (is_numeric( $number )&&$number!=-1) { echo $number; } else { _e( '(Unknown - Save the configuration)', LPTEXTDOMAIN ); } ?></b></i></p>
    
    <input type="hidden" name="widget-linked-pages[<?php echo $number; ?>][submit]" value="1" />
    
    <h3><?php _e( 'Link Field', LPTEXTDOMAIN ); ?></h3>
    <p>
        <?php _e( 'Enter the custom field which will be used to find pages that link here', LPTEXTDOMAIN ); ?>
    </p>
    <p>
        <label for="linked-pages-field-field-<?php echo $number; ?>"><?php _e( 'Link field:', LPTEXTDOMAIN ); ?></label>
        <input id="linked-pages-field-field-<?php echo $number; ?>" name="widget-linked-pages[<?php echo $number; ?>][field]" class="widefat" type="text" value="<?php echo $field; ?>" />
    </p>
    <h3><?php _e( 'Widget Text', LPTEXTDOMAIN ); ?></h3>    
    <p>
        <?php _e( 'Here you can specify a title, title wrappers, body text and the widget wrappers for the widget', LPTEXTDOMAIN ); ?>
    </p>
    <p>
        <label for="linked-pages-field-pretitle-<?php echo $number; ?>"><?php _e( 'Pre Title Wrapper:', LPTEXTDOMAIN ); ?></label>
        <input id="linked-pages-field-pretitle-<?php echo $number; ?>" name="widget-linked-pages[<?php echo $number; ?>][pretitle]" class="widefat" type="text" value="<?php echo $pretitle; ?>" />
    </p>
    <p>
        <label for="linked-pages-field-title-<?php echo $number; ?>"><?php _e( 'Widget Title:', LPTEXTDOMAIN ); ?></label>
        <input id="linked-pages-field-title-<?php echo $number; ?>" name="widget-linked-pages[<?php echo $number; ?>][title]" class="widefat" type="text" value="<?php echo $title; ?>" />
    </p>
    <p><?php _e( 'The title can contain %1$s which will be replaced with the post ID and %2$s which will be replace with the post title.', LPTEXTDOMAIN ); ?></p>
    <p>
        <label for="linked-pages-field-posttitle-<?php echo $number; ?>"><?php _e( 'Post Title Wrapper:', LPTEXTDOMAIN ); ?></label>
        <input id="linked-pages-field-posttitle-<?php echo $number; ?>" name="widget-linked-pages[<?php echo $number; ?>][posttitle]" class="widefat" type="text" value="<?php echo $posttitle; ?>" />
    </p>
    <p>
        <label for="linked-pages-field-body-<?php echo $number; ?>"><?php _e( 'Body Text:', LPTEXTDOMAIN ); ?></label>
        <textarea id="linked-pages-field-body-<?php echo $number; ?>" name="widget-linked-pages[<?php echo $number; ?>][body]" class="code widefat" rows="5" cols="20"><?php echo $body; ?></textarea>
    </p>
    <p><?php _e( 'The body text can contain %1$s which will be replaced with the post ID and %2$s which will be replace with the post title.', LPTEXTDOMAIN ); ?></p>    
    <p>
        <label for="linked-pages-field-prewidget-<?php echo $number; ?>"><?php _e( 'Pre Widget Wrapper:', LPTEXTDOMAIN ); ?></label>
        <input id="linked-pages-field-prewidget-<?php echo $number; ?>" name="widget-linked-pages[<?php echo $number; ?>][prewidget]" class="widefat" type="text" value="<?php echo $prewidget; ?>" />
    </p>
    <p>
        <label for="linked-pages-field-postwidget-<?php echo $number; ?>"><?php _e( 'Post Widget Wrapper:', LPTEXTDOMAIN ); ?></label>
        <input id="linked-pages-field-postwidget-<?php echo $number; ?>" name="widget-linked-pages[<?php echo $number; ?>][postwidget]" class="widefat" type="text" value="<?php echo $postwidget; ?>" />
    </p>
    <p><?php _e( '<b>Note:</b> Under normal circumstances, you should not change the widget wrappers!', LPTEXTDOMAIN ); ?></p>
    
    <h3><?php _e( 'Links', LPTEXTDOMAIN ); ?></h3> 
    <p>
        <?php _e( 'Here you can specify the link format, link breaker (inserted between two links - if using a list as a wrapper, this is not needed) and the link list wrappers', LPTEXTDOMAIN ); ?>
    </p>
    <p>
        <label for="linked-pages-field-prelinks-<?php echo $number; ?>"><?php _e( 'Pre Link Wrapper:', LPTEXTDOMAIN ); ?></label>
        <input id="linked-pages-field-prelinks-<?php echo $number; ?>" name="widget-linked-pages[<?php echo $number; ?>][prelinks]" class="widefat" type="text" value="<?php echo $prelinks; ?>" />
    </p>
    <p>
        <label for="linked-pages-field-link-<?php echo $number; ?>"><?php _e( 'Link Format:', LPTEXTDOMAIN ); ?></label>
        <input id="linked-pages-field-link-<?php echo $number; ?>" name="widget-linked-pages[<?php echo $number; ?>][link]" class="widefat" type="text" value="<?php echo $link; ?>" />
    </p>
    <p><?php _e( 'Link format should specify the whole link (including any list item wrappers you may require).  %1$s is converted into the destination page URL and %2$s is converted into the destination page title.', LPTEXTDOMAIN ); ?></p>
    <p>
        <label for="linked-pages-field-linkbreak-<?php echo $number; ?>"><?php _e( 'Link Breaker:', LPTEXTDOMAIN ); ?></label>
        <input id="linked-pages-field-linkbreak-<?php echo $number; ?>" name="widget-linked-pages[<?php echo $number; ?>][linkbreak]" class="widefat" type="text" value="<?php echo $linkbreak; ?>" />
    </p>
    <p>
        <label for="linked-pages-field-postlinks-<?php echo $number; ?>"><?php _e( 'Post Link Wrapper:', LPTEXTDOMAIN ); ?></label>
        <input id="linked-pages-field-postlinks-<?php echo $number; ?>" name="widget-linked-pages[<?php echo $number; ?>][postlinks]" class="widefat" type="text" value="<?php echo $postlinks; ?>" />
    </p>
    
    <h3><?php _e( 'Search For Similar' , LPTEXTDOMAIN ); ?></h3>
    <p><?php _e( 'This functionality allows you to find other pages that have the same link field value as the page being displayed.  The current page can either remain in the list as an active link, text or it can be removed.', LPTEXTDOMAIN ); ?></p>    
    <p>
        <label for="linked-pages-searchforsimilar-<?php echo $number; ?>"><?php _e( 'Search for similar:', LPTEXTDOMAIN ); ?></label>
        <input type="checkbox" id="linked-pages-searchforsimilar-<?php echo $number; ?>" name="widget-linked-pages[<?php echo $number; ?>][searchforsimilar]"<?php if ($searchforsimilar) { echo " checked"; } ?> />
    </p>
    <p>
        <label for="linked-pages-field-similarlinkmode-<?php echo $number; ?>"><?php _e('Current Post Display Mode:', LPTEXTDOMAIN ); ?></label>
        <select id="linked-pages-field-similarlinkmode-<?php echo $number; ?>" name="widget-linked-pages[<?php echo $number; ?>][similarlinkmode]" class="widefat">
            <option value="0"<?php if ($similarlinkmode==0) { echo " selected"; } ?>><?php _e( 'Appear in list', LPTEXTDOMAIN ); ?></option>
            <option value="1"<?php if ($similarlinkmode==1) { echo " selected"; } ?>><?php _e( 'Remove from list', LPTEXTDOMAIN ); ?></option>
        </select>
    </p>
    <p>
        <label for="linked-pages-field-similarlink-<?php echo $number; ?>"><?php _e( 'Link Format:', LPTEXTDOMAIN ); ?></label>
        <input id="linked-pages-field-similarlink-<?php echo $number; ?>" name="widget-linked-pages[<?php echo $number; ?>][similarlink]" class="widefat" type="text" value="<?php echo $similarlink; ?>" />
    </p>
    <p><?php _e( 'When set to \'Appear in list\', this link format will be used unless it is blank.  This allows you to apply special formatting to the current page link.', LPTEXTDOMAIN ); ?></p>
    
    <h3><?php _e( 'Help and Assistance', LPTEXTDOMAIN ); ?></h3>
    <p>
        <?php printf( __( 'For assistance with Linked Pages, post your comments on <a href="%1$s" target="_BLANK">my blog</a>, and to read the on-line user manual visit <a href="%2$s" target="_BLANK">my wiki</a>.  <i>Thanks, AthenaOfDelphi</i>', LPTEXTDOMAIN ), LPBLOGLINK, LPWIKILINK ) ?>
    </p>
    <hr />
    </div>
    
    <?php
    // And we're finished with our widget options panel
}

//-------------------------------------------------------------------------------------------------------------------------------------------

// Function to allow rendering of widgets directly in a theme
function linkedpages( $number ) {
    wp_widget_linked_pages( array( 'number' => $number ), 1 );
}

// Function to add widget option table when activating this plugin
function wp_widget_linked_pages_activation() {
    add_option( LPOPTIONS, '', '', 'yes' );
    add_option( LPLINKEROPTIONS, '', '', 'yes' );
}

//-------------------------------------------------------------------------------------------------------------------------------------------

// Function to initialize the Custom Field Widget: the widget and widget options panel
function wp_widget_linked_pages_register() {
    // Do we have options? If so, get info as array
    if ( !$options = get_option( LPOPTIONS ) )
        $options = array();
    // Variables for our widget
    $widget_ops = array(
            'classname'   => 'widget_linked_pages',
            'description' => __( 'Display links to pages/posts which reference the current page using a custom field', LPTEXTDOMAIN )
        );
    // Variables for our widget options panel
    $control_ops = array(
            'width'   => 700,
            'height'  => 450,
            'id_base' => 'linked-pages'
        );
    // Variable for out widget name
    $name = __( 'Linked Pages', LPTEXTDOMAIN );
    // Assume we have no widgets in play.
    $id = false;
    // Since we're dealing with multiple widgets, we much register each accordingly
    foreach ( array_keys( $options ) as $o ) {
        // Per Automattic: "Old widgets can have null values for some reason"
        if ( ! isset( $options[$o]['field'] ) || ! isset( $options[$o]['title'] ) ) {
            continue;
        }
            
        // Automattic told me not to translate an ID. Ever.
        $id = "linked-pages-$o"; // "Never never never translate an id" See?
        // Register the widget and then the widget options menu
        wp_register_sidebar_widget( $id, $name, 'wp_widget_linked_pages', $widget_ops, array( 'number' => $o ) );
        wp_register_widget_control( $id, $name, 'wp_widget_linked_pages_control', $control_ops, array( 'number' => $o ) );
    }
    // Create a generic widget if none are in use
    if ( !$id ) {
        // Register the widget and then the widget options menu
        wp_register_sidebar_widget( 'linked-pages-1', $name, 'wp_widget_linked_pages', $widget_ops, array( 'number' => -1 ) );
        wp_register_widget_control( 'linked-pages-1', $name, 'wp_widget_linked_pages_control', $control_ops, array( 'number' => -1 ) );
    }
    
}

//-------------------------------------------------------------------------------------------------------------------------------------------

// Adds filters to custom field values to prettify like other content
//add_filter( 'linked_pages_value1', 'convert_chars' );
//add_filter( 'linked_pages_value2', 'stripslashes' );
//add_filter( 'linked_pages_value3', 'wptexturize' );

// When activating, run the appropriate function
register_activation_hook( __FILE__, 'wp_widget_linked_pages_activation' );

// Allow localization, if applicable
$plugin_dir = dirname( plugin_basename( __FILE__ ) );
load_plugin_textdomain( LPTEXTDOMAIN, 'wp-content/plugins/' . $plugin_dir, $plugin_dir );

// Initializes the function to make our widget(s) available
add_action( 'init', 'wp_widget_linked_pages_register' );
    
?>