<div class="header">
    <div class="user-info">
        Tervetuloa, <?php echo htmlspecialchars($_SESSION['user_id']); ?>!
    </div>
    
    <div class="header-controls">
        <button id="menuButton" class="menu-button">Valikko</button>
        <a href="#" id="logoutLink" class="logout-link">Kirjaudu ulos</a>
    </div>
    
    <!-- Menu-popup -->
    <div id="menu" class="menu" style="display: none;">
        <ul>
            <li><a href="upload.php" <?php echo basename($_SERVER['PHP_SELF']) == 'upload.php' ? 'class="active"' : ''; ?>>Etusivu</a></li>
            <li><a href="files.php" <?php echo basename($_SERVER['PHP_SELF']) == 'files.php' ? 'class="active"' : ''; ?>>Tiedostot</a></li>
            <li><a href="settings.php" <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'class="active"' : ''; ?>>Asetukset</a></li>
            <li><a href="help.php" <?php echo basename($_SERVER['PHP_SELF']) == 'help.php' ? 'class="active"' : ''; ?>>Ohjeet</a></li>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <li><a href="manage_users.php" <?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'class="active"' : ''; ?>>Muokkaa Käyttäjiä</a></li>
            <li><a href="user_files.php" <?php echo basename($_SERVER['PHP_SELF']) == 'user_files.php' ? 'class="active"' : ''; ?>>Käyttäjien tiedostot</a></li>
            <?php endif; ?>
        </ul>
    </div>
    
    <!-- Uloskirjautumisen varmistusikkuna - Enhanced with better styling -->
    <div id="logoutConfirm" class="logout-confirm" style="display: none;">
        <div class="logout-confirm-content">
            <h3>Kirjaudu ulos</h3>
            <p>Oletko varma että haluat kirjautua ulos ?</p>
            <div class="logout-confirm-buttons">
                <button class="logout-confirm-yes" onclick="confirmLogout()">Kyllä !</button>
                <button class="logout-confirm-no" onclick="cancelLogout()">En</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Enhanced logout confirmation styling */
.logout-confirm {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.logout-confirm-content {
  background: linear-gradient(to bottom, #ffffff, #f9f0ff);
  border-radius: 12px;
  padding: 25px;
  width: 90%;
  max-width: 400px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
  text-align: center;
  animation: popIn 0.3s ease-out forwards;
  border: 1px solid #e0d0ff;
}

@keyframes popIn {
  0% { transform: scale(0.8); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}

.logout-confirm h3 {
  color: #8400cc;
  margin-top: 0;
  font-size: 22px;
  border-bottom: 2px solid #f0e0ff;
  padding-bottom: 10px;
}

.logout-confirm p {
  margin: 20px 0;
  font-size: 16px;
  color: #555;
}

.logout-confirm-buttons {
  display: flex;
  justify-content: center;
  gap: 15px;
  margin-top: 20px;
}

.logout-confirm-yes {
  background: linear-gradient(to bottom, #ff6b6b,rgb(255, 0, 0));
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 6px;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 3px 6px rgba(238, 82, 83, 0.3);
}

.logout-confirm-no {
  background: linear-gradient(to bottom,rgb(119, 0, 255),rgb(132, 1, 255));
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 6px;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 3px 6px rgba(108, 92, 231, 0.3);
}

.logout-confirm-yes:hover {
  transform: translateY(-2px);
  box-shadow: 0 5px 10px rgba(238, 82, 83, 0.4);
}

.logout-confirm-no:hover {
  transform: translateY(-2px);
  box-shadow: 0 5px 10px rgba(108, 92, 231, 0.4);
}

.pop-out {
  animation: popOut 0.3s ease-in forwards;
}

@keyframes popOut {
  0% { transform: scale(1); opacity: 1; }
  100% { transform: scale(0.8); opacity: 0; }
}
</style>

<script>
// Korjattu valikon toiminta - toimii kaikilla sivuilla
document.addEventListener("DOMContentLoaded", function() {
  // Menu toggle functionality
  const menuButton = document.getElementById("menuButton");
  const menu = document.getElementById("menu");
  
  if (menuButton && menu) {
      menuButton.addEventListener("click", function(e) {
          e.preventDefault();
          e.stopPropagation();
          
          // Toggle menu visibility with inline styles for better compatibility
          if (menu.style.display === "none" || menu.style.display === "") {
              menu.style.display = "block";
              menu.style.opacity = "1";
              menu.style.transform = "scale(1)";
              menu.style.pointerEvents = "auto";
          } else {
              menu.style.opacity = "0";
              menu.style.transform = "scale(0.95)";
              menu.style.pointerEvents = "none";
              
              // Delay hiding to allow animation to complete
              setTimeout(() => {
                  menu.style.display = "none";
              }, 300);
          }
      });
      
      // Close menu when clicking outside
      document.addEventListener("click", function(e) {
          if (menu.style.display === "block" && !menu.contains(e.target) && e.target !== menuButton) {
              menu.style.opacity = "0";
              menu.style.transform = "scale(0.95)";
              menu.style.pointerEvents = "none";
              
              // Delay hiding to allow animation to complete
              setTimeout(() => {
                  menu.style.display = "none";
              }, 300);
          }
      });
  }
  
  // Logout confirmation
  const logoutLink = document.getElementById("logoutLink");
  const logoutConfirm = document.getElementById("logoutConfirm");
  
  if (logoutLink && logoutConfirm) {
      logoutLink.addEventListener("click", function(e) {
          e.preventDefault();
          logoutConfirm.style.display = "flex";
      });
  }
});

// Uloskirjautumisen varmistustoiminnot
function confirmLogout() {
  window.location.href = "logout.php";
}

function cancelLogout() {
  var logoutConfirm = document.getElementById("logoutConfirm");
  var logoutContent = document.querySelector(".logout-confirm-content");
  
  // Add animation for closing
  logoutContent.classList.add("pop-out");
  
  // Hide after animation completes
  setTimeout(() => {
      logoutConfirm.style.display = "none";
      logoutContent.classList.remove("pop-out");
  }, 300);
}
</script>

