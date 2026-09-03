/**
 * Global JavaScript & Theme Toggle Manager
 */

(function () {
  // Apply saved theme immediately before render
  const savedTheme = localStorage.getItem('theme') || 'dark';
  document.documentElement.setAttribute('data-theme', savedTheme);
})();

document.addEventListener('DOMContentLoaded', function () {
  const currentTheme = localStorage.getItem('theme') || 'dark';
  updateToggleButtons(currentTheme);
});

function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme') || 'dark';
  const newTheme = current === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', newTheme);
  localStorage.setItem('theme', newTheme);
  updateToggleButtons(newTheme);
}

function updateToggleButtons(theme) {
  const btns = document.querySelectorAll('.theme-toggle-btn');
  btns.forEach((btn) => {
    if (theme === 'light') {
      btn.innerHTML = '🌙 Dark Mode';
    } else {
      btn.innerHTML = '☀️ Light Mode';
    }
  });
}
