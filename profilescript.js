document.getElementById('change-password-btn').addEventListener('click', function() {
    event.preventDefault();
    document.getElementById('password-section').style.display = 'none';
    document.getElementById('change-password-section').style.display = 'block';
    document.getElementById('submit').classList.toggle('hidden');
    document.getElementById('profile-details').classList.toggle('reorg');
});

document.getElementById('cancel-password-btn').addEventListener('click', function() {
    event.preventDefault();
    document.getElementById('password-section').style.display = 'flex';
    document.getElementById('change-password-section').style.display = 'none';
    document.getElementById('submit').classList.toggle('hidden');
    document.getElementById('profile-details').classList.toggle('reorg');
});

document.getElementById('profilepic').addEventListener('click', function() {
    event.preventDefault();
    document.getElementById('picselection').classList.toggle('hidden');
});

const pictures = document.querySelectorAll('[id^="newpic"]');
pictures.forEach(function(picture) {
    picture.addEventListener('click', function() {
        picture.classList.remove('newpic');
    });
});

document.addEventListener("DOMContentLoaded", function() {
    const isMobileDevice = /Mobi|Android/i.test(navigator.userAgent);

    if (isMobileDevice) {
        // Get all profile pictures (locked and unlocked)
        const profilePictures = document.querySelectorAll('.profpic');

        profilePictures.forEach(picture => {
            let timeoutId;  // To store the timeout reference

            picture.addEventListener('click', function(event) {
                const isLocked = this.classList.contains('locked');

                if (isLocked) {
                    // For locked images, prevent default behavior (don't allow selection)
                    event.preventDefault();
                }

                // Find the nearest unlock-info div after the image
                const unlockInfo = this.closest('label').querySelector('.unlock-info');

                if (unlockInfo) {
                    // Toggle the display of the unlock info
                    unlockInfo.style.display = (unlockInfo.style.display === 'block') ? 'none' : 'block';

                    // If the unlock-info is displayed, set a timeout to hide it after 3 seconds
                    if (unlockInfo.style.display === 'block') {
                        // Clear any previous timeout to avoid multiple timeouts overlapping
                        clearTimeout(timeoutId);

                        // Set the timeout to hide the unlock-info after 3 seconds
                        timeoutId = setTimeout(() => {
                            unlockInfo.style.display = 'none';
                        }, 2000); // 2000 milliseconds = 3 seconds
                    }
                }
            });
        });
    }
});
