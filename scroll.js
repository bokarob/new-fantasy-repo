// Check if scroll position exists in localStorage
if (localStorage.getItem('scrollPosition')) {
    // Restore scroll position on page load
    window.scrollTo(0, parseInt(localStorage.getItem('scrollPosition')));
  }
  
  // Listen for scroll events
  window.addEventListener('scroll', function() {
    // Store scroll position in localStorage
    localStorage.setItem('scrollPosition', window.scrollY.toString());
  });
  
  // Reset scroll position when form is submitted
  document.getElementById('yourFormId').addEventListener('submit', function() {
    localStorage.removeItem('scrollPosition');
  });