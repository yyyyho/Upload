document.addEventListener("DOMContentLoaded", () => {
  // Theme toggle functionality (if on settings page)
  const themeSelect = document.getElementById("theme")
  if (themeSelect) {
    themeSelect.addEventListener("change", function () {
      document.body.className = this.value + "-theme"
    })
  }

  // File sorting functionality (if on files page)
  const fileSortSelect = document.getElementById("fileSort")
  if (fileSortSelect) {
    fileSortSelect.addEventListener("change", function () {
      // You could implement AJAX sorting here, or just submit the form
      this.form.submit()
    })
  }

  // Add animation classes to elements
  const animateElements = () => {
    // Add fade-in animation to list items
    const listItems = document.querySelectorAll(".file-list li, .selected-file-list li")
    listItems.forEach((item, index) => {
      item.style.animationDelay = `${index * 0.05}s`
      item.classList.add("animated")
    })

    // Add animation to buttons
    const buttons = document.querySelectorAll('button, .button, input[type="submit"]')
    buttons.forEach((button) => {
      button.addEventListener("mouseenter", () => {
        button.style.transform = "translateY(-3px)"
        button.style.boxShadow = "0 4px 8px rgba(166, 0, 255, 0.3)"
      })
      button.addEventListener("mouseleave", () => {
        button.style.transform = "translateY(0)"
        button.style.boxShadow = ""
      })
    })
  }

  // Run animations
  animateElements()
})

// Helper function for formatting file sizes
function formatFileSize(bytes) {
  if (bytes < 1024) {
    return bytes + " B"
  } else if (bytes < 1048576) {
    return (bytes / 1024).toFixed(1) + " KB"
  } else {
    return (bytes / 1048576).toFixed(1) + " MB"
  }
}

// Uloskirjautumisen varmistus
function confirmLogout() {
  window.location.href = "logout.php"
}

function cancelLogout() {
  const logoutConfirm = document.getElementById("logoutConfirm")
  const logoutContent = document.querySelector(".logout-confirm-content")

  // Lisätään animaatio sulkemiselle
  logoutContent.classList.add("pop-out")

  // Suljetaan ikkuna animaation jälkeen
  setTimeout(() => {
    logoutConfirm.style.display = "none"
    logoutContent.classList.remove("pop-out")
  }, 300)
}

