<?php
/**
 * Plugin name: Post files organizer
 * Description: Create pseudo-directories to organize documents attached to the post
 * Version: 1.0
 * Author: Cau Guanabara
 * Author URI: mailto:cauguanabara@gmail.com
 * Text Domain: pfo
 * Domain Path: /langs/
 * License: Wordpress
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PFO_URL', str_replace("\\", "/", plugin_dir_url(__FILE__)));

class PostFilesOrganizer {

    public function __construct() {
        global $require_zip_plugin;
        if ($require_zip_plugin) {
            $require_zip_plugin->require(
                'Post Files Organizer', 
                'WP Helper', 
                'https://github.com/caugbr/wp-helper/archive/refs/heads/main.zip', 
                'wp-helper/wp-helper.php'
            );
        }
        global $wp_helper;
        if ($wp_helper) {
            $wp_helper->add_textdomain('pfo', dirname(plugin_basename(__FILE__)) . '/langs/');
            $wp_helper->load("popup", '__return_true', 'both');
            $wp_helper->load("dialog", '__return_true', 'both');

            add_action('wp_enqueue_scripts', [$this, 'add_js_css']);
            add_action('admin_enqueue_scripts', [$this, 'add_js_css']);
            add_action('add_meta_boxes', [$this, 'add_pfo_metabox']);
            add_action('save_post', [$this, 'save_directory']);
        }
    }

    public function add_js_css() {
        if (is_admin()) {
            wp_enqueue_script('pfo-js', PFO_URL . '/assets/js/pfo-admin.js');
            wp_localize_script('pfo-js', 'pfoStrings', [
                "createDir" => __("Create directory", "pfo"),
                "subDirOf" => __("Sub directory of: ", "pfo"),
                "none" => __("None", "pfo"),
                "saveMsg" => __("When you click Ok, your publication will be saved.", "pfo"),
                "saveTitle" => __("Directory name", "pfo"),
                "removeMsg" => __("Do you really want to remove the %s directory?", "pfo")
            ]);
        } else {
            wp_enqueue_script('pfo-js', PFO_URL . '/assets/js/pfo.js');
        }
        wp_enqueue_style('pfo-admin', PFO_URL . '/assets/css/pfo.css');
    }

    public function add_pfo_metabox($ptype) {
        add_meta_box('pfo_box', __("Attached files", "pfo"), [$this, 'pfo_metabox'], 'page');
    }

    public function pfo_metabox($post) {
        $directories = $this->get_directory_names($post->ID);
        $combo = '';
        if (count($directories)) {
            $combo = __("Move selected files to:", "pfo") . " <select name='directories'>\n";
            $combo .= "<option value=''>" . __("Select...", "pfo") . "</option>\n";
            foreach ($directories as $name) {
                $combo .= "<option value='{$name}'>{$name}</option>\n";
            }
            $combo .= "</select>\n";
            $combo .= '<button type="button" class="move-files button button-secondary" disabled>' . __("Move", "pfo") . '</button>';
        }
        ?>
        <p><?php _e("Files attached to this publication", "pfo"); ?></p>
        <p style="text-align: right; margin-top: 4px">
            <input type="hidden" name="new_directory">
            <input type="hidden" name="new_directory_parent">
            <input type="hidden" name="move_to_directory">
            <input type="hidden" name="remove_directory">
            <?php print $combo; ?>
            <button type="button" class='new-directory button button-secondary'>
                <?php _e("Create directory", "pfo"); ?>
            </button>
            <label for='insert-media-button' class='button button-secondary'>
                <?php _e("Send files", "pfo"); ?>
            </label>
        </p>
        <?php
        $this->download_html($post->ID);
    }

    public function download_html($pid, $ret = false) {
        $dirs = $this->get_directories($pid);
        $names = $this->get_directory_names($pid);
        $atts = get_posts(['post_type' => 'attachment', 'posts_per_page' => -1, 'post_parent' => $pid]);
        $htm = '<ul class="downloads"><li class="empty">' . __("No files to show", "pfo") . '</li></ul>';
        if (count($atts)) {
            $htm = "<ul class='downloads'>\n";
            foreach ($dirs as $name => $ids) {
                $dname = array_shift($names);
                $icon = '<i class="far fa-folder"></i>';
                $htm .= "<li>\n<a class='dir' data-id='{$dname}'><strong>{$icon} {$name}</strong>\n";
                $htm .= "<span class='desc'>" . __("Directory", "pfo") . "</span>\n";
                $htm .= "<span class='remove-dir'>" . __("Remove directory", "pfo") . "</span></a>\n";
                $htm .= "<ul class='folder-items'>\n";
                foreach ($ids as $ind => $attid) {
                    if (is_array($attid)) {
                        $dname = array_shift($names);
                        $htm .= $this->dir_html($dname, $attid, $atts);
                        continue;
                    }
                    $att = $this->filter_att($atts, $attid);
                    if ($att) {
                        $atts = array_filter($atts, function($e) use($att) { return $e->ID != $att->ID; });
                        $icon = $this->file_type_icon($att->guid);
                        $desc = $att->post_content;
                        $htm .= "<li>\n<a data-id='{$att->ID}' href='{$att->guid}'>{$icon} {$att->post_title} ";
                        $htm .= "<span class='desc'>{$desc}</span></a>\n</li>\n";
                    }
                }
                $htm .= "</ul>\n</li>\n";
            }
            if (count($atts)) {
                foreach ($atts as $att) {
                    if (!preg_match("/^image/", $att->post_mime_type)) {
                        $icon = $this->file_type_icon($att->guid);
                        $desc = $att->post_content;
                        $htm .= "<li>\n<a data-id='{$att->ID}' href='{$att->guid}'>{$icon} {$att->post_title} ";
                        $htm .= "<span class='desc'>{$desc}</span></a>\n</li>\n";
                    }
                }
            }
            $htm .= "</ul>\n";
        }
        if ($ret) {
            return $htm;
        }
        print $htm;
    }
    
    private function file_type_icon($path) {
        $parts = explode(".", $path);
        $ext = $parts[count($parts) - 1];
        $icons = [
            "pdf" => "fas fa-file-pdf",
            "doc" => "far fa-file-word",
            "docx" => "far fa-file-word",
            "ppt" => "far fa-file-powerpoint",
            "xls" => "far fa-file-excel",
            "avi" => "fas fa-file-video",
            "mpeg" => "fas fa-file-video",
            "mp4" => "fas fa-file-video",
            "mp3" => "fas fa-file-audio",
            "zip" => "far fa-file-archive",
            "txt" => "far fa-file"
        ];
        $cls = isset($icons[$ext]) ? $icons[$ext] : $icons['txt'];
        return "<i class='{$cls}'></i>";
    }
    
    public function save_directory($post_id) {
        if (!empty($_POST['remove_directory'])) {
            $this->remove_directory($post_id, $_POST['remove_directory']);
        }
        if (!empty($_POST['new_directory'])) {
            $this->add_directory($post_id, $_POST['new_directory'], $_POST['new_directory_parent'] ?? '');
        }
        if (!empty($_POST['move_to_directory']) && !empty($_POST['files_to_move'])) {
            $this->add_files_to_directory($post_id, $_POST['move_to_directory'], $_POST['files_to_move']);
        }
    }

    public function remove_directory($post_id, $dir_name) {
        $dirs = $this->get_directories($post_id);
        $parts = explode('/', $dir_name);
        $this->rec_remove_directory($dirs, $parts);
        update_post_meta($post_id, 'directories', $dirs);
    }
    
    public function rec_remove_directory(&$arr, $parts) {
        $current_dir = array_shift($parts);
        if (isset($arr[$current_dir])) {
            if (!empty($parts)) {
                $this->rec_remove_directory($arr[$current_dir], $parts);
            } else {
                unset($arr[$current_dir]);
            }
        }
    }

    public function add_directory($post_id, $dir_name, $parent) {
        $dirs = $this->get_directories($post_id);
        if (!empty($parent)) {
            $parts = explode('/', $parent);
            $current = &$dirs;
            foreach ($parts as $part) {
                if (!isset($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }
            $current[$dir_name] = [];
        } else {
            $dirs[$dir_name] = [];
        }
        update_post_meta($post_id, 'directories', $dirs);
    }
    
    public function get_directories($post_id) {
        $dirs = get_post_meta($post_id, 'directories', true);
        if (!is_array($dirs)) {
            $dirs = [];
        }
        return $dirs;
    }
    
    public function get_directory_names($post_id) {
        $dirs = $this->get_directories($post_id);
        return $this->rec_get_dir_names($dirs);
    }
    
    private function rec_get_dir_names($array, $prefix = '') {
        $directories = [];
        foreach ($array as $key => $value) {
            if (is_string($key)) {
                $currentPath = $prefix ? $prefix . '/' . $key : $key;
                $directories[] = $currentPath;
                if (is_array($value)) {
                    $directories = array_merge($directories, $this->rec_get_dir_names($value, $currentPath));
                }
            }
        }
        return $directories;
    }

    public function add_files_to_directory($post_id, $dir_name, $files) {
        $this->remove_from_all_directories($post_id, $files);
        $dirs = $this->get_directories($post_id);
        $parts = explode('/', $dir_name);
        $current = &$dirs;
        foreach ($parts as $part) {
            if (!isset($current[$part])) {
                $current[$part] = [];
            }
            $current = &$current[$part];
        }
        $current = array_merge($current, $files);
        update_post_meta($post_id, 'directories', $dirs);
    }
    
    
    private function remove_from_all_directories($post_id, $files) {
        $dirs = $this->get_directories($post_id);
        foreach ($files as $att_id) {
            $dirs = $this->rec_remove_from_all_directories($att_id, $dirs);
        }
        update_post_meta($post_id, 'directories', $dirs);
    }
    
    private function rec_remove_from_all_directories($att_id, $arr) {
        foreach ($arr as $ind => $item) {
            if (is_array($item)) {
                $arr[$ind] = $this->rec_remove_from_all_directories($att_id, $item);
            } else {
                if ($att_id == $item) {
                    unset($arr[$ind]);
                }
            }
        }
        return $arr;
    }
    
    private function filter_att($atts, $attid) {
        foreach ($atts as $att) {
            if ($att->ID == $attid) {
                return $att;
            }
        }
        return NULL;
    }
    
    private function dir_html($name, $files, &$atts) {
        $path = $name;
        $parts = explode("/", $name);
        $name = $parts[count($parts) - 1];
        
        $icon = '<i class="far fa-folder"></i>';
        $htm = "<li>\n<a class='dir' data-id='{$path}'><strong>{$icon} {$name}</strong>\n";
        $htm .= "<span class='desc'>" . __("Directory", "pfo") . "</span>\n";
        $htm .= "<span class='remove-dir'>" . __("Remove directory", "pfo") . "</span></a>\n";
        $htm .= "<ul class='folder-items'>\n";
        foreach ($files as $ind => $attid) {
            if (is_string($ind)) {
                $htm .= $this->dir_html($path . "/" . $ind, $attid, $atts);
                continue;
            }
            $att = $this->filter_att($atts, $attid);
            if ($att) {
                $atts = array_filter($atts, function($e) use($att) { return $e->ID != $att->ID; });
                $icon = $this->file_type_icon($att->guid);
                $desc = $att->post_content;
                $htm .= "<li><a data-id='{$att->ID}' href='{$att->guid}'>{$icon} {$att->post_title} <span class='desc'>{$desc}</span></a></li>\n";
            }
        }
        $htm .= "</ul>\n</li>\n";
        return $htm;
    }
}

global $post_files_organizer;
$post_files_organizer = new PostFilesOrganizer();