<?php
/**
 *
 * @author    Shahid Malla
 * @website   https://shahidmalla.com
 * @version   4.0
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
        
        // Return CSS and JavaScript that will run when the page loads
        return "
        <link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css\">
        <style>
            /* Hide existing menus */
            .navbar-right, .right-sidebar-menu-container {
                display: none !important;
            }
            
            /* Main menu container */
            #main-menu-container {
                display: flex;
                justify-content: center;
                width: 100%;
                background-color: #f8f9fa;
                border-bottom: 1px solid #e9ecef;
                position: relative;
                z-index: 100;
                padding: 0 15px;
            }
            
            /* Main navigation */
            .custom-main-nav {
                display: flex;
                flex-wrap: wrap;
                list-style: none;
                margin: 0;
                padding: 0;
                width: 100%;
                justify-content: flex-start;
            }
            
            /* Menu items */
            .custom-main-nav .nav-item {
                margin: 0 5px;
            }
            
            /* Links */
            .custom-main-nav .nav-link {
                display: flex;
                align-items: center;
                padding: 15px 20px;
                color: #1a4b72;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.3s ease;
                border-radius: 4px;
                white-space: nowrap;
            }
            
            .custom-main-nav .nav-link:hover {
                background-color: #1a4b72;
                color: white;
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            
            /* Icons */
            .custom-main-nav .nav-link i {
                margin-right: 8px;
                font-size: 1.1em;
            }
            
            /* Dropdown menus */
            .custom-main-nav .dropdown-menu {
                border-radius: 4px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                border: none;
                padding: 8px;
                min-width: 200px;
            }
            
            .custom-main-nav .dropdown-item {
                padding: 10px 15px;
                border-radius: 3px;
                transition: all 0.2s ease;
                color: #1a4b72;
            }
            
            .custom-main-nav .dropdown-item:hover {
                background-color: #e9f0f7;
                transform: translateX(3px);
            }
            
            /* Mobile menu toggle */
            .custom-mobile-toggle {
                display: none;
                background: #1a4b72;
                color: white;
                border: none;
                padding: 10px;
                border-radius: 4px;
                cursor: pointer;
                margin: 10px 0;
                width: 100%;
                text-align: left;
            }
            
            /* Responsive styles */
            @media (max-width: 991px) {
                .custom-mobile-toggle {
                    display: block;
                }
                
                .custom-main-nav {
                    flex-direction: column;
                    width: 100%;
                    display: none;
                }
                
                .custom-main-nav.show {
                    display: flex;
                }
                
                .custom-main-nav .nav-item {
                    width: 100%;
                    margin: 2px 0;
                }
                
                .custom-main-nav .nav-link {
                    width: 100%;
                    justify-content: flex-start;
                }
                
                .custom-main-nav .dropdown-menu {
                    position: static;
                    float: none;
                    width: 100%;
                    margin-top: 0;
                    box-shadow: none;
                    border-radius: 0;
                }
                
                #main-menu-container {
                    flex-direction: column;
                    padding: 0;
                }
            }
        </style>
        
        <script type=\"text/javascript\">
        document.addEventListener(\"DOMContentLoaded\", function() {
            // Menu data from PHP
            var menuData = " . $menusJson . ";
            
            // Function to create main menu
            function createMainMenu() {
                // Create menu container
                var menuContainer = document.createElement('div');
                menuContainer.id = 'main-menu-container';
                
                // Create mobile toggle button
                var mobileToggle = document.createElement('button');
                mobileToggle.className = 'custom-mobile-toggle';
                mobileToggle.innerHTML = '<i class=\"fas fa-bars\"></i> Menu';
                mobileToggle.onclick = function() {
                    var nav = document.querySelector('.custom-main-nav');
                    if (nav.classList.contains('show')) {
                        nav.classList.remove('show');
                    } else {
                        nav.classList.add('show');
                    }
                };
                
                menuContainer.appendChild(mobileToggle);
                
                // Create main navigation
                var mainNav = document.createElement('ul');
                mainNav.className = 'custom-main-nav';
                
                // Add menu items
                for (var parentId in menuData.parents) {
                    var parent = menuData.parents[parentId];
                    var children = menuData.children[parentId] || [];
                    
                    var menuItem = document.createElement('li');
                    menuItem.className = 'nav-item' + (children.length > 0 ? ' dropdown' : '');
                    
                    var menuLink = document.createElement('a');
                    menuLink.className = 'nav-link' + (children.length > 0 ? ' dropdown-toggle' : '');
                    menuLink.href = parent.url;
                    
                    if (children.length > 0) {
                        menuLink.setAttribute('data-toggle', 'dropdown');
                        menuLink.setAttribute('role', 'button');
                        menuLink.setAttribute('aria-haspopup', 'true');
                        menuLink.setAttribute('aria-expanded', 'false');
                    }
                    
                    if (parent.icon) {
                        var icon = document.createElement('i');
                        icon.className = parent.icon;
                        menuLink.appendChild(icon);
                    }
                    
                    menuLink.appendChild(document.createTextNode(' ' + parent.name));
                    menuItem.appendChild(menuLink);
                    
                    // Add children if any
                    if (children.length > 0) {
                        var dropdown = document.createElement('div');
                        dropdown.className = 'dropdown-menu';
                        
                        for (var i = 0; i < children.length; i++) {
                            var child = children[i];
                            var childLink = document.createElement('a');
                            childLink.className = 'dropdown-item';
                            childLink.href = child.url;
                            
                            if (child.icon) {
                                var childIcon = document.createElement('i');
                                childIcon.className = child.icon;
                                childLink.appendChild(childIcon);
                                childLink.appendChild(document.createTextNode(' '));
                            }
                            
                            childLink.appendChild(document.createTextNode(child.name));
                            dropdown.appendChild(childLink);
                        }
                        
                        menuItem.appendChild(dropdown);
                    }
                    
                    mainNav.appendChild(menuItem);
                }
                
                menuContainer.appendChild(mainNav);
                return menuContainer;
            }
            
            // Wait for the page to be fully loaded
            setTimeout(function() {
                // Find where to insert the menu
                var header = document.querySelector('header');
                var logo = document.querySelector('.logo');
                var target = null;
                
                if (logo) {
                    target = logo.parentNode;
                } else if (header) {
                    target = header;
                } else {
                    var navbar = document.querySelector('.navbar');
                    if (navbar) {
                        target = navbar.parentNode;
                    }
                }
                
                // If target found, insert menu
                if (target) {
                    var mainMenu = createMainMenu();
                    
                    // Hide existing menus in the header area
                    var existingMenus = document.querySelectorAll('.navbar-right, .right-sidebar-menu-container');
                    existingMenus.forEach(function(menu) {
                        menu.style.display = 'none';
                    });
                    
                    // Insert our menu after the target
                    if (target.nextSibling) {
                        target.parentNode.insertBefore(mainMenu, target.nextSibling);
                    } else {
                        target.parentNode.appendChild(mainMenu);
                    }
                    
                    // Initialize any dropdown menus if Bootstrap is available
                    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.dropdown !== 'undefined') {
                        jQuery('.dropdown-toggle').dropdown();
                    }
                } else {
                    console.log('Menu Manager: Could not find target for menu insertion');
                }
            }, 300);
        });
        </script>";
    } catch (\Exception $e) {
        // Log error
        logActivity("Menu Manager Error: " . $e->getMessage());
        return "";
    }
});
