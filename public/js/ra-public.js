// ra-public.js
window.addEventListener("unhandledrejection", function (event) {
  // Prevent jQuery Migrate's null Promise rejection from showing as an error
  if (event.reason === null) {
    event.preventDefault();
  }
});

window.RAPublicUtils = {
  VALID_TYPES: Object.freeze({
    SUCCESS: "success",
    ERROR: "error",
    WARNING: "warning",
    INFO: "info",
  }),

  showOverlay: function (message, messageType = "info", duration = 1500) {
    const overlay = document.createElement("div");
    overlay.className = "ra-overlay";

    const messageDiv = document.createElement("div");
    messageDiv.className = "ra-message";

    if (messageType && Object.values(this.VALID_TYPES).includes(messageType)) {
      messageDiv.classList.add(`ra-message--${messageType}`);
    }

    messageDiv.textContent = message;
    overlay.appendChild(messageDiv);
    document.body.appendChild(overlay);

    requestAnimationFrame(() => (overlay.style.opacity = "1"));
    setTimeout(() => {
      overlay.style.opacity = "0";
      setTimeout(() => overlay.remove(), 500);
    }, duration);
  },

  waitForRecorder: function () {
    console.log("waitForRecorder called");
    return new Promise((resolve) => {
      // First check: Is RecorderManager defined?
      console.log("RecorderManager exists:", !!window.RecorderManager);

      if (window.RecorderManager && window.RecorderManager.isReady()) {
        console.log("Recorder already ready");
        const instance = window.RecorderManager.getInstance();
        console.log("Got recorder instance:", !!instance);
        resolve(instance);
        return;
      }

      console.log("Waiting for recorder to be ready...");
      let attempts = 0;
      const maxAttempts = 10;

      const checkRecorder = () => {
        attempts++;
        console.log(`Checking recorder (attempt ${attempts}/${maxAttempts})`);

        if (window.RecorderManager && window.RecorderManager.isReady()) {
          console.log("Recorder became ready");
          const instance = window.RecorderManager.getInstance();
          console.log("Got recorder instance:", !!instance);
          resolve(instance);
          return;
        }

        if (attempts < maxAttempts) {
          setTimeout(checkRecorder, 500);
        } else {
          console.error("Recorder failed to initialize after maximum attempts");
          resolve(null);
        }
      };

      checkRecorder();
    });
  },

  async updateRecorderState(passageId) {
    try {
      console.log("updateRecorderState called for passage:", passageId);
      const recorder = await this.waitForRecorder();

      if (recorder) {
        console.log("Updating recorder for passage:", passageId);
        recorder.handlePassageChange(passageId);
      } else {
        console.error("Recorder not available after waiting");
      }
    } catch (error) {
      console.error("Error updating recorder state:", error);
    }
  },

  initCollapsible: function () {
    console.log("Initializing collapsible elements");
    const titles = document.querySelectorAll(".ra-collapsible-title");
    console.log("Found collapsible titles:", titles.length);

    titles.forEach((title) => {
      title.addEventListener("click", async (event) => {
        event.stopPropagation();

        const passageId = title.dataset.passageId;
        if (!passageId) {
          console.log("No passage ID found");
          return;
        }

        console.log("Handling click for passage:", passageId);

        // Close other passages
        document
          .querySelectorAll(".ra-collapsible-title")
          .forEach((otherTitle) => {
            if (otherTitle !== title) {
              otherTitle.classList.remove("ra-collapsible-title--active");
              const otherContent = document.getElementById(
                "passage-" + otherTitle.dataset.passageId
              );
              if (otherContent) {
                otherContent.classList.remove("ra-collapsible-content--active");
              }
            }
          });

        // Toggle current
        const content = document.getElementById("passage-" + passageId);
        if (content) {
          content.classList.toggle("ra-collapsible-content--active");
          title.classList.toggle("ra-collapsible-title--active");
        }

        // Update hidden input and recorder state
        const currentPassageInput =
          document.getElementById("current-passage-id");
        if (currentPassageInput) {
          const oldValue = currentPassageInput.value;
          currentPassageInput.value = passageId;

          if (oldValue !== passageId) {
            console.log("Passage changed from", oldValue, "to", passageId);
            await this.updateRecorderState(passageId);
          }
        }
      });
    });
  },
};

document.addEventListener("DOMContentLoaded", function () {
  console.log("DOM Content Loaded - Initializing RAPublicUtils");
  RAPublicUtils.initCollapsible();

  // Check if WaveSurfer is available
  console.log("WaveSurfer availability:", {
    wavesurfer: !!window.WaveSurfer,
    regions: !!window.WaveSurfer?.regions,
  });

  if (new URLSearchParams(window.location.search).get("login") === "success") {
    RAPublicUtils.showOverlay("Du är inloggad.", "success");
  }
});
