document.querySelector('.back-to-top a').addEventListener('click', function(e) {
    e.preventDefault();
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  });


let clickCount = 0;
const maxClicks = 5;

const hiddenTextElement = document.getElementById("heart");
const footerBottomText = document.getElementById("footerbottomtext");
hiddenTextElement.addEventListener("click", function() {
    clickCount++;

    if (clickCount === maxClicks) {
      const oldHidden = footerBottomText.innerHTML;
      footerBottomText.innerHTML = "Meg a faszt!";
      timeoutId = setTimeout(() => {
        footerBottomText.innerHTML = oldHidden;
        document.location.href='index.php?holmes=ON';
      }, 5000);
      clickCount = 0;
      
    }
});