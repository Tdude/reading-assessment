// for PUBLIC script
window.RAPublicUtils = {
  initCollapsible: function () {
    document.querySelectorAll(".ra-collapsible-title").forEach((title) => {
      title.addEventListener("click", function () {
        const content = document.getElementById(this.dataset.target);
        const isVisible = content.classList.contains("show");

        // Toggle content
        content.classList.toggle("show");
        this.classList.toggle("active");
      });
    });
  },
};

document.addEventListener("DOMContentLoaded", function () {
  RAPublicUtils.initCollapsible();
});
