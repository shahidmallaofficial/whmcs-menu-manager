<?php
/**
 * WHMCS Menu Manager Module
 *
 * @author    Shahid Malla
 * @website   https://shahidmalla.com
 * @version   4.0
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

/**
 * Define module configuration
 */
function menumanager_config() {
    return [
        'name' => 'Menu Manager',
        'description' => 'Customizes navigation menus for guests and clients with Font Awesome icons',
        'author' => 'Shahid Malla',
        'website' => 'https://shahidmalla.com',
        'version' => '4.0',
        'fields' => []
    ];
}

/**
 * Activation function - creates database tables
 */
function menumanager_activate() {
    try {
        if (!Capsule::schema()->hasTable('mod_menumanager')) {
            Capsule::schema()->create('mod_menumanager', function ($table) {
                $table->increments('id');
                $table->string('menu_type')->default('client'); // client or guest
                $table->string('menu_name');
                $table->string('menu_url');
                $table->string('menu_icon')->nullable();
                $table->integer('menu_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_custom')->default(false);
                $table->string('parent_id')->nullable();
                $table->timestamps();
            });
            
            // Insert default menus
            populateDefaultMenus();
        }

        // Create hook file
        createJsHookFile();
        
        // Clear template cache
        clearAllCaches();
        
        return [
            'status' => 'success',
            'description' => 'Menu Manager module successfully activated'
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Unable to activate module: ' . $e->getMessage()
        ];
    }
}

/**
 * Deactivation function
 */
function menumanager_deactivate() {
    try {
        // We leave the database tables intact but remove hook files
        $hookFile = ROOTDIR . '/includes/hooks/menumanager.php';
        if (file_exists($hookFile)) {
            @unlink($hookFile);
        }
        
        return [
            'status' => 'success',
            'description' => 'Menu Manager module successfully deactivated'
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Error deactivating module: ' . $e->getMessage()
        ];
    }
}

/**
 * Create JavaScript-based hook file
 */
function createJsHookFile() {
    // Ensure the hooks directory exists
    $hooksDir = ROOTDIR . '/includes/hooks/';
    if (!is_dir($hooksDir)) {
        mkdir($hooksDir, 0755, true);
    }

    // Simple hook file that only injects JavaScript
    $hookContent = '<?php
/**
 * Menu Manager Hook File
 *
 * @author    Shahid Malla
 * @website   https://shahidmalla.com
 */

use WHMCS\Database\Capsule;

// Prevent direct access
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Add menu JavaScript to the client area header
 */
add_hook("ClientAreaHeaderOutput", 1, function($vars) {
    // Determine if user is logged in
    $isLoggedIn = (isset($_SESSION["uid"]) && !empty($_SESSION["uid"]));
    $menuType = $isLoggedIn ? "client" : "guest";
    
    try {
        // Get active menu items
        $menus = Capsule::table("mod_menumanager")
            ->where("menu_type", $menuType)
            ->where("is_active", 1)
            ->orderBy("menu_order", "asc")
            ->get();
        
        if (count($menus) == 0) {
            return "";
        }
        
        // Convert menus to JSON for JavaScript
        $parentMenus = [];
        $childMenus = [];
        
        foreach ($menus as $menu) {
            if (empty($menu->parent_id)) {
                $parentMenus[$menu->id] = [
                    "id" => $menu->id,
                    "name" => $menu->menu_name,
                    "url" => $menu->menu_url,
                    "icon" => $menu->menu_icon,
                    "order" => $menu->menu_order
                ];
            } else {
                if (!isset($childMenus[$menu->parent_id])) {
                    $childMenus[$menu->parent_id] = [];
                }
                
                $childMenus[$menu->parent_id][] = [
                    "id" => $menu->id,
                    "name" => $menu->menu_name,
                    "url" => $menu->menu_url,
                    "icon" => $menu->menu_icon,
                    "order" => $menu->menu_order
                ];
            }
        }
        
        // Sort parent menus by order
        uasort($parentMenus, function($a, $b) {
            return $a["order"] - $b["order"];
        });
        
        // Sort child menus
        foreach ($childMenus as &$children) {
            usort($children, function($a, $b) {
                return $a["order"] - $b["order"];
            });
        }
        
        // Convert to JSON
        $menusJson = json_encode([
            "parents" => $parentMenus,
            "children" => $childMenus
        ]);
        
        // Return JavaScript that will run when the page loads
        return "
        <link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css\">
        <script type=\"text/javascript\">
        document.addEventListener(\"DOMContentLoaded\", function() {
            // Menu data from PHP
            var menuData = " . $menusJson . ";
            
            // Wait for the page to be fully loaded
            setTimeout(function() {
                // Find the navigation menu
                var navSelectors = [
                    \".navbar-nav\", 
                    \"#Primary_Navbar\", 
                    \"#main-menu\", 
                    \".navbar-main\",
                    \"nav.navbar ul.navbar-nav\"
                ];
                
                var navMenu = null;
                for (var i = 0; i < navSelectors.length; i++) {
                    var elements = document.querySelectorAll(navSelectors[i]);
                    if (elements.length > 0) {
                        navMenu = elements[0];
                        break;
                    }
                }
                
                if (!navMenu) {
                    console.log(\"Menu Manager: Could not find navigation menu\");
                    return;
                }
                
                // Clear existing menu items
                navMenu.innerHTML = \"\";
                
                // Add our custom menu items
                for (var parentId in menuData.parents) {
                    var parent = menuData.parents[parentId];
                    var children = menuData.children[parentId] || [];
                    
                    var menuItem = document.createElement(\"li\");
                    menuItem.className = \"nav-item\" + (children.length > 0 ? \" dropdown\" : \"\");
                    
                    var menuLink = document.createElement(\"a\");
                    menuLink.className = \"nav-link\" + (children.length > 0 ? \" dropdown-toggle\" : \"\");
                    menuLink.href = parent.url;
                    
                    if (children.length > 0) {
                        menuLink.setAttribute(\"data-toggle\", \"dropdown\");
                        menuLink.setAttribute(\"role\", \"button\");
                        menuLink.setAttribute(\"aria-haspopup\", \"true\");
                        menuLink.setAttribute(\"aria-expanded\", \"false\");
                    }
                    
                    if (parent.icon) {
                        var icon = document.createElement(\"i\");
                        icon.className = parent.icon + \" fa-fw mr-1\";
                        menuLink.appendChild(icon);
                    }
                    
                    menuLink.appendChild(document.createTextNode(parent.name));
                    menuItem.appendChild(menuLink);
                    
                    // Add children if any
                    if (children.length > 0) {
                        var dropdown = document.createElement(\"div\");
                        dropdown.className = \"dropdown-menu\";
                        
                        for (var i = 0; i < children.length; i++) {
                            var child = children[i];
                            var childLink = document.createElement(\"a\");
                            childLink.className = \"dropdown-item\";
                            childLink.href = child.url;
                            
                            if (child.icon) {
                                var childIcon = document.createElement(\"i\");
                                childIcon.className = child.icon + \" fa-fw mr-1\";
                                childLink.appendChild(childIcon);
                            }
                            
                            childLink.appendChild(document.createTextNode(child.name));
                            dropdown.appendChild(childLink);
                        }
                        
                        menuItem.appendChild(dropdown);
                    }
                    
                    navMenu.appendChild(menuItem);
                }
                
                // Initialize any dropdown menus if Bootstrap is available
                if (typeof jQuery !== \"undefined\" && typeof jQuery.fn.dropdown !== \"undefined\") {
                    jQuery(\".dropdown-toggle\").dropdown();
                }
            }, 300); // Small delay to ensure the DOM is fully loaded
        });
        </script>";
    } catch (\Exception $e) {
        // Log error
        logActivity("Menu Manager Error: " . $e->getMessage());
        return "";
    }
});';

    $hookFile = ROOTDIR . '/includes/hooks/menumanager.php';
    file_put_contents($hookFile, $hookContent);
}

/**
 * Clear all caches
 */
function clearAllCaches() {
    // Clear template cache
    $cacheDir = ROOTDIR . '/templates_c/';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
    
    // Clear system cache
    $systemCacheDir = ROOTDIR . '/storage/app/system/';
    if (is_dir($systemCacheDir)) {
        $files = glob($systemCacheDir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}

/**
 * Admin area output
 */
function menumanager_output($vars) {
    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
    $menuType = isset($_REQUEST['menu_type']) ? $_REQUEST['menu_type'] : 'client';
    
    if ($action == 'save') {
        handleSaveMenu();
    } elseif ($action == 'delete') {
        handleDeleteMenu();
    } elseif ($action == 'clearcache') {
        clearAllCaches();
        echo '<div class="alert alert-success">All caches cleared successfully</div>';
    } elseif ($action == 'resetmodule') {
        // Re-create hook file
        createJsHookFile();
        clearAllCaches();
        echo '<div class="alert alert-success">Module files recreated and caches cleared</div>';
    }
    
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
    
    echo '<div class="menu-manager-container">';
    echo '<h2>WHMCS Menu Manager</h2>';
    
    // Version check
    $whmcsVersion = isset($GLOBALS['CONFIG']['Version']) ? $GLOBALS['CONFIG']['Version'] : 'Unknown';
    echo '<div class="alert alert-info">
            <strong>System Information:</strong> WHMCS Version ' . $whmcsVersion . ', Menu Manager v4.0
          </div>';
    
    // Top action buttons
    echo '<div class="row" style="margin-bottom: 20px;">
            <div class="col-md-12">
                <a href="addonmodules.php?module=menumanager&action=clearcache" class="btn btn-warning">
                    <i class="fas fa-sync"></i> Clear All Caches
                </a>
                <a href="addonmodules.php?module=menumanager&action=resetmodule" class="btn btn-danger">
                    <i class="fas fa-redo"></i> Reset Module Files
                </a>
                <a href="' . $vars['modulelink'] . '" class="btn btn-default">
                    <i class="fas fa-refresh"></i> Refresh Page
                </a>
            </div>
          </div>';
    
    // Menu type tabs
    echo '<ul class="nav nav-tabs" role="tablist">';
    echo '<li role="presentation" class="' . ($menuType == 'client' ? 'active' : '') . '">
            <a href="addonmodules.php?module=menumanager&menu_type=client">Client Menu</a>
          </li>';
    echo '<li role="presentation" class="' . ($menuType == 'guest' ? 'active' : '') . '">
            <a href="addonmodules.php?module=menumanager&menu_type=guest">Guest Menu</a>
          </li>';
    echo '</ul>';
    
    // Display current menus
    echo '<div class="tab-content">';
    echo '<div role="tabpanel" class="tab-pane active">';
    
    // Current menus table
    echo '<h3>Current ' . ucfirst($menuType) . ' Menus</h3>';
    echo '<div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>URL</th>
                        <th>Icon</th>
                        <th>Order</th>
                        <th>Parent</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';
    
    // Get all menus of the selected type
    $menus = Capsule::table('mod_menumanager')
        ->where('menu_type', $menuType)
        ->orderBy('menu_order', 'asc')
        ->get();
    
    // Generate parent menu options for dropdown
    $parentOptions = '<option value="">None (Top Level)</option>';
    foreach ($menus as $parentMenu) {
        if (empty($parentMenu->parent_id)) {
            $parentOptions .= '<option value="' . $parentMenu->id . '">' . $parentMenu->menu_name . '</option>';
        }
    }
    
    foreach ($menus as $menu) {
        $parentName = '';
        if (!empty($menu->parent_id)) {
            $parent = Capsule::table('mod_menumanager')->where('id', $menu->parent_id)->first();
            $parentName = $parent ? $parent->menu_name : 'Invalid Parent';
        }
        
        echo '<tr>
                <td>' . htmlspecialchars($menu->menu_name) . '</td>
                <td>' . htmlspecialchars($menu->menu_url) . '</td>
                <td><i class="' . $menu->menu_icon . '"></i> ' . htmlspecialchars($menu->menu_icon) . '</td>
                <td>' . $menu->menu_order . '</td>
                <td>' . $parentName . '</td>
                <td>' . ($menu->is_active ? 'Active' : 'Inactive') . '</td>
                <td>
                    <a href="#" class="btn btn-sm btn-primary edit-menu" 
                        data-id="' . $menu->id . '" 
                        data-name="' . htmlspecialchars($menu->menu_name) . '" 
                        data-url="' . htmlspecialchars($menu->menu_url) . '" 
                        data-icon="' . htmlspecialchars($menu->menu_icon) . '" 
                        data-order="' . $menu->menu_order . '" 
                        data-parent="' . $menu->parent_id . '" 
                        data-active="' . $menu->is_active . '">
                        Edit
                    </a>
                    <a href="addonmodules.php?module=menumanager&action=delete&id=' . $menu->id . '&menu_type=' . $menuType . '" 
                        class="btn btn-sm btn-danger" 
                        onclick="return confirm(\'Are you sure you want to delete this menu item?\')">
                        Delete
                    </a>
                </td>
            </tr>';
    }
    
    echo '</tbody></table></div>';
    
    // Add/Edit menu form
    echo '<div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><span id="form-title">Add New</span> Menu Item</h3>
            </div>
            <div class="panel-body">
                <form method="post" action="addonmodules.php?module=menumanager&action=save&menu_type=' . $menuType . '">
                    <input type="hidden" name="id" id="menu-id" value="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="menu-name">Menu Name</label>
                                <input type="text" class="form-control" id="menu-name" name="menu_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="menu-url">URL</label>
                                <input type="text" class="form-control" id="menu-url" name="menu_url" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="menu-icon">Font Awesome Icon</label>
                                <input type="text" class="form-control" id="menu-icon" name="menu_icon" placeholder="fas fa-home">
                                <small class="text-muted">Visit <a href="https://fontawesome.com/icons" target="_blank">Font Awesome</a> for icons</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="menu-order">Order</label>
                                <input type="number" class="form-control" id="menu-order" name="menu_order" value="0" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="parent-id">Parent Menu</label>
                                <select class="form-control" id="parent-id" name="parent_id">
                                    ' . $parentOptions . '
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="is-active">Status</label>
                                <select class="form-control" id="is-active" name="is_active">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Save Menu Item</button>
                        <button type="button" class="btn btn-default" id="reset-form">Reset Form</button>
                    </div>
                </form>
            </div>
        </div>';
        
    // Help section
    echo '<div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">Help & Troubleshooting</h3>
            </div>
            <div class="panel-body">
                <p><strong>Menu Manager</strong> allows you to customize navigation menus for guests and clients.</p>
                
                <h4>Features</h4>
                <ul>
                    <li><strong>Client Menu</strong>: Displayed when users are logged in</li>
                    <li><strong>Guest Menu</strong>: Displayed for visitors who are not logged in</li>
                    <li><strong>Font Awesome Icons</strong>: Add icons by using the appropriate Font Awesome class (e.g., "fas fa-home")</li>
                    <li><strong>Parent Menu</strong>: Create dropdown menus by setting a parent for menu items</li>
                </ul>
                
                <h4>Troubleshooting Steps</h4>
                <p>If menu changes aren\'t visible on the frontend:</p>
                <ol>
                    <li>Click "Clear All Caches" button above</li>
                    <li>Temporarily disable template caching: Go to System Settings > General Settings > System Tab > Template Cache: Never Cache</li>
                    <li>Click "Reset Module Files" to regenerate all module files</li>
                    <li>Try accessing your site in an incognito/private browsing window</li>
                </ol>
                
                <h4>Common URL Examples</h4>
                <table class="table table-striped table-sm">
                    <tr>
                        <td><strong>Home:</strong></td>
                        <td>index.php</td>
                    </tr>
                    <tr>
                        <td><strong>Client Area:</strong></td>
                        <td>clientarea.php</td>
                    </tr>
                    <tr>
                        <td><strong>Services:</strong></td>
                        <td>clientarea.php?action=services</td>
                    </tr>
                    <tr>
                        <td><strong>Domains:</strong></td>
                        <td>clientarea.php?action=domains</td>
                    </tr>
                    <tr>
                        <td><strong>Invoices:</strong></td>
                        <td>clientarea.php?action=invoices</td>
                    </tr>
                    <tr>
                        <td><strong>Support Tickets:</strong></td>
                        <td>clientarea.php?action=tickets</td>
                    </tr>
                    <tr>
                        <td><strong>Announcements:</strong></td>
                        <td>index.php?rp=/announcements</td>
                    </tr>
                    <tr>
                        <td><strong>Knowledgebase:</strong></td>
                        <td>index.php?rp=/knowledgebase</td>
                    </tr>
                    <tr>
                        <td><strong>Network Status:</strong></td>
                        <td>index.php?rp=/serverstatus</td>
                    </tr>
                    <tr>
                        <td><strong>Contact Us:</strong></td>
                        <td>index.php?rp=/contact</td>
                    </tr>
                </table>
                
                <p>Developed by <a href="https://shahidmalla.com" target="_blank">Shahid Malla</a></p>
            </div>
        </div>';
    
    echo '</div>'; // close tab-pane
    echo '</div>'; // close tab-content
    echo '</div>'; // close container
    
    // JavaScript for the admin interface
    echo '<script type="text/javascript">
        $(document).ready(function() {
            // Edit menu button click
            $(".edit-menu").click(function(e) {
                e.preventDefault();
                var id = $(this).data("id");
                var name = $(this).data("name");
                var url = $(this).data("url");
                var icon = $(this).data("icon");
                var order = $(this).data("order");
                var parent = $(this).data("parent");
                var active = $(this).data("active");
                
                $("#form-title").text("Edit");
                $("#menu-id").val(id);
                $("#menu-name").val(name);
                $("#menu-url").val(url);
                $("#menu-icon").val(icon);
                $("#menu-order").val(order);
                $("#parent-id").val(parent);
                $("#is-active").val(active);
            });
            
            // Reset form button
            $("#reset-form").click(function() {
                $("#form-title").text("Add New");
                $("#menu-id").val("");
                $("#menu-name").val("");
                $("#menu-url").val("");
                $("#menu-icon").val("");
                $("#menu-order").val(0);
                $("#parent-id").val("");
                $("#is-active").val(1);
            });
        });
    </script>';
}

/**
 * Handle saving menu items
 */
function handleSaveMenu() {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
    $menuType = isset($_REQUEST['menu_type']) ? $_REQUEST['menu_type'] : 'client';
    
    $data = [
        'menu_type' => $menuType,
        'menu_name' => $_POST['menu_name'],
        'menu_url' => $_POST['menu_url'],
        'menu_icon' => $_POST['menu_icon'],
        'menu_order' => (int)$_POST['menu_order'],
        'parent_id' => !empty($_POST['parent_id']) ? $_POST['parent_id'] : null,
        'is_active' => (int)$_POST['is_active'],
        'is_custom' => true,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    try {
        if ($id) {
            // Check for circular reference
            if (!empty($data['parent_id']) && $data['parent_id'] == $id) {
                echo '<div class="alert alert-danger">Error: A menu item cannot be its own parent</div>';
                return;
            }
            
            // Update existing menu
            Capsule::table('mod_menumanager')
                ->where('id', $id)
                ->update($data);
            $message = 'Menu item updated successfully';
        } else {
            // Add new menu
            $data['created_at'] = date('Y-m-d H:i:s');
            Capsule::table('mod_menumanager')->insert($data);
            $message = 'Menu item added successfully';
        }
        
        // Clear all caches
        clearAllCaches();
        
        echo '<div class="alert alert-success">' . $message . '</div>';
    } catch (\Exception $e) {
        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

/**
 * Handle deleting menu items
 */
function handleDeleteMenu() {
    $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
    
    try {
        // Check if this menu has children
        $children = Capsule::table('mod_menumanager')
            ->where('parent_id', $id)
            ->count();
            
        if ($children > 0) {
            echo '<div class="alert alert-danger">Cannot delete menu item with child items. Please remove or reassign child items first.</div>';
            return;
        }
        
        Capsule::table('mod_menumanager')->where('id', $id)->delete();
        
        // Clear all caches
        clearAllCaches();
        
        echo '<div class="alert alert-success">Menu item deleted successfully</div>';
    } catch (\Exception $e) {
        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

/**
 * Populate default menus on activation
 */
function populateDefaultMenus() {
    $defaultClientMenus = [
        ['menu_name' => 'Home', 'menu_url' => 'index.php', 'menu_icon' => 'fas fa-home', 'menu_order' => 10],
        ['menu_name' => 'Services', 'menu_url' => 'clientarea.php?action=services', 'menu_icon' => 'fas fa-cube', 'menu_order' => 20],
        ['menu_name' => 'Domains', 'menu_url' => 'clientarea.php?action=domains', 'menu_icon' => 'fas fa-globe', 'menu_order' => 30],
        ['menu_name' => 'Billing', 'menu_url' => 'clientarea.php?action=invoices', 'menu_icon' => 'fas fa-credit-card', 'menu_order' => 40],
        ['menu_name' => 'Support', 'menu_url' => 'clientarea.php?action=tickets', 'menu_icon' => 'fas fa-life-ring', 'menu_order' => 50],
    ];
    
    $defaultGuestMenus = [
        ['menu_name' => 'Home', 'menu_url' => 'index.php', 'menu_icon' => 'fas fa-home', 'menu_order' => 10],
        ['menu_name' => 'Store', 'menu_url' => 'cart.php', 'menu_icon' => 'fas fa-shopping-cart', 'menu_order' => 20],
        ['menu_name' => 'Announcements', 'menu_url' => 'index.php?rp=/announcements', 'menu_icon' => 'fas fa-bullhorn', 'menu_order' => 30],
        ['menu_name' => 'Knowledgebase', 'menu_url' => 'index.php?rp=/knowledgebase', 'menu_icon' => 'fas fa-book', 'menu_order' => 40],
        ['menu_name' => 'Contact Us', 'menu_url' => 'index.php?rp=/contact', 'menu_icon' => 'fas fa-envelope', 'menu_order' => 50],
    ];
    
    $now = date('Y-m-d H:i:s');
    
    foreach ($defaultClientMenus as $menu) {
        Capsule::table('mod_menumanager')->insert([
            'menu_type' => 'client',
            'menu_name' => $menu['menu_name'],
            'menu_url' => $menu['menu_url'],
            'menu_icon' => $menu['menu_icon'],
            'menu_order' => $menu['menu_order'],
            'is_active' => true,
            'is_custom' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
    
    foreach ($defaultGuestMenus as $menu) {
        Capsule::table('mod_menumanager')->insert([
            'menu_type' => 'guest',
            'menu_name' => $menu['menu_name'],
            'menu_url' => $menu['menu_url'],
            'menu_icon' => $menu['menu_icon'],
            'menu_order' => $menu['menu_order'],
            'is_active' => true,
            'is_custom' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

/**
 * Admin area sidebar
 */
function menumanager_sidebar($vars) {
    $modulelink = $vars['modulelink'];
    
    return '<div class="sidebar-header">
                <i class="fas fa-bars"></i> Menu Manager
            </div>
            <ul class="menu">
                <li><a href="' . $modulelink . '&menu_type=client"><i class="fas fa-user"></i> Client Menu</a></li>
                <li><a href="' . $modulelink . '&menu_type=guest"><i class="fas fa-user-times"></i> Guest Menu</a></li>
                <li><a href="' . $modulelink . '&action=clearcache"><i class="fas fa-sync"></i> Clear Cache</a></li>
                <li><a href="' . $modulelink . '&action=resetmodule"><i class="fas fa-redo"></i> Reset Module</a></li>
            </ul>';
}
