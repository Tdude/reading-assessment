window.RAPublicUtils = {
  initCollapsible: function () {
    document.querySelectorAll(".ra-collapsible-title").forEach((title) => {
      title.addEventListener("click", function () {
        // Get passage ID first
        const passageId = this.dataset.passageId;
        if (passageId) {
          const contentId = "passage-" + passageId;
          const content = document.getElementById(contentId);

          if (content) {
            // Toggle content
            content.classList.toggle("show");
            this.classList.toggle("active");

            // Update current passage input
            const currentPassageInput =
              document.getElementById("current-passage-id");
            if (currentPassageInput) {
              currentPassageInput.value = passageId;
              console.log("Selected passage ID:", passageId);
            }
          }
        }
      });
    });
  },
};

// Initialize when document is ready
document.addEventListener("DOMContentLoaded", function () {
  RAPublicUtils.initCollapsible();
});
