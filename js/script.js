
document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Get the toggle button and the body element
    const themeToggle = document.getElementById('themeToggle');
    const body = document.body;
    const icon = themeToggle ? themeToggle.querySelector('i') : null;

    // 2. Check Local Storage to remember user choice
    // If the user previously chose dark mode, apply it immediately
    if (localStorage.getItem('theme') === 'dark') {
        body.classList.add('dark-mode');
        if(icon) {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        }
        if(themeToggle) themeToggle.innerHTML = '<i class="fas fa-sun"></i> Light Mode';
    }

    // 3. Add Click Event Listener
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            
            // Toggle the class on the body
            body.classList.toggle('dark-mode');
            
            // Update the Icon and Text
            if (body.classList.contains('dark-mode')) {
                localStorage.setItem('theme', 'dark'); // Save preference
                this.innerHTML = '<i class="fas fa-sun"></i> Light Mode';
            } else {
                localStorage.setItem('theme', 'light'); // Save preference
                this.innerHTML = '<i class="fas fa-moon"></i> Dark Mode';
            }
        });
    }

    // 4. Dynamic Date (Bonus: Fixes the "Loading date..." text)
    const dateBox = document.getElementById('currentDate');
    if (dateBox) {
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        dateBox.innerText = new Date().toLocaleDateString('en-US', options);
    }
});