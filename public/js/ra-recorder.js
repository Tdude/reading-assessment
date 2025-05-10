//ra-recorder.js
class ReadingAssessmentRecorder {
  constructor(container) {
    console.log("Initializing ReadingAssessmentRecorder");
    console.log("WaveSurfer availability:", !!window.WaveSurfer);
    console.log(
      "WaveSurfer Regions availability:",
      !!window.WaveSurfer?.regions
    );

    if (!container) {
      console.error("Container is required for ReadingAssessmentRecorder");
      return;
    }

    // Core properties
    this.container = container;
    this.mediaStream = null;
    this.recorder = null;
    this.audioBlob = null;
    this.wavesurfer = null;
    this.region = null;
    this.isRecording = false;

    // Get UI elements with debug logging
    this.startBtn = container.querySelector("#start-recording");
    this.stopBtn = container.querySelector("#stop-recording");
    this.playBtn = container.querySelector("#playback");
    this.trimBtn = container.querySelector("#trim-audio");
    this.uploadBtn = container.querySelector("#upload-recording");
    this.status = container.querySelector("#status");
    this.waveformContainer = container.querySelector("#waveform");

    console.log("UI Elements found:", {
      startBtn: !!this.startBtn,
      stopBtn: !!this.stopBtn,
      playBtn: !!this.playBtn,
      trimBtn: !!this.trimBtn,
      uploadBtn: !!this.uploadBtn,
      status: !!this.status,
      waveformContainer: !!this.waveformContainer,
    });

    // Create loading overlay
    this.loadingOverlay = document.createElement("div");
    this.loadingOverlay.className = "ra-loading-overlay";
    // Basic styling (can be enhanced with CSS in your plugin's stylesheet)
    this.loadingOverlay.style.position = "absolute";
    this.loadingOverlay.style.top = "0";
    this.loadingOverlay.style.left = "0";
    this.loadingOverlay.style.width = "100%";
    this.loadingOverlay.style.height = "100%";
    this.loadingOverlay.style.backgroundColor = "rgba(255, 255, 255, 0.85)";
    this.loadingOverlay.style.zIndex = "1000"; // Ensure it's on top
    this.loadingOverlay.style.display = "flex"; // Use flex for centering
    this.loadingOverlay.style.alignItems = "center";
    this.loadingOverlay.style.justifyContent = "center";
    this.loadingOverlay.style.textAlign = "center";
    this.loadingOverlay.style.display = "none"; // Initially hidden

    this.loadingMessageElement = document.createElement("span");
    this.loadingMessageElement.className = "ra-loading-message";
    this.loadingMessageElement.style.padding = "20px";
    this.loadingMessageElement.style.backgroundColor = "#fff";
    this.loadingMessageElement.style.border = "1px solid #ddd";
    this.loadingMessageElement.style.borderRadius = "5px";
    this.loadingMessageElement.style.boxShadow = "0 2px 5px rgba(0,0,0,0.1)";

    this.loadingOverlay.appendChild(this.loadingMessageElement);

    if (this.container) { // Ensure container exists before appending
      this.container.style.position = "relative"; // For overlay positioning
      this.container.appendChild(this.loadingOverlay);
    }

    // Validate and initialize
    if (!this.validateElements()) {
      console.error("Required UI elements not found");
      return;
    }

    this.initializeButtonStates();
    this.bindEventHandlers();
    this.checkInitialPassage();
  }

  validateElements() {
    const isValid =
      this.startBtn &&
      this.stopBtn &&
      this.playBtn &&
      this.trimBtn &&
      this.uploadBtn &&
      this.status &&
      this.waveformContainer;
    console.log("Elements validation result:", isValid);
    return isValid;
  }

  initializeButtonStates() {
    console.log("Initializing button states");
    this.startBtn.disabled = false;
    this.stopBtn.disabled = true;
    this.playBtn.disabled = true;
    this.trimBtn.disabled = true;
    this.uploadBtn.disabled = true;
  }

  async startRecording() {
    console.log("Start recording triggered");
    try {
      console.log("Requesting media stream...");
      this.mediaStream = await navigator.mediaDevices.getUserMedia({
        audio: true,
      });
      console.log("Media stream obtained:", !!this.mediaStream);

      this.recorder = new MediaRecorder(this.mediaStream);
      console.log("MediaRecorder created:", !!this.recorder);

      this.isRecording = true;

      const chunks = [];
      this.recorder.ondataavailable = (e) => {
        console.log("Data chunk available:", e.data.size, "bytes");
        chunks.push(e.data);
      };

      this.recorder.onstop = async () => {
        console.log("Recording stopped, processing chunks...");
        await this.handleRecordingStop(chunks);
      };

      this.recorder.start();
      console.log("Recording started");
      this.updateUIForRecording(true);
    } catch (error) {
      console.error("Recording failed:", error);
      this.status.textContent = `Kunde inte starta inspelningen: ${error.message}`;
    }
  }

  bindEventHandlers() {
    this.startBtn.onclick = () => this.startRecording();
    this.stopBtn.onclick = () => this.stopRecording();
    this.playBtn.onclick = () => this.togglePlayback();
    this.trimBtn.onclick = () => this.trimAudio();
    this.uploadBtn.onclick = () => this.uploadRecording();
  }

  checkInitialPassage() {
    const currentPassageId =
      document.getElementById("current-passage-id")?.value;
    if (currentPassageId) {
      this.handlePassageChange(currentPassageId);
    } else {
      this.startBtn.disabled = true;
      this.status.textContent = "Välj en text innan du börjar spela in.";
    }
  }

  stopRecording() {
    if (this.recorder?.state === "recording") {
      this.recorder.stop();
      this.mediaStream.getTracks().forEach((track) => track.stop());
    }
  }

  updateUIForRecording(isRecording) {
    this.startBtn.disabled = isRecording;
    this.stopBtn.disabled = !isRecording;
    this.status.textContent = isRecording
      ? "Spelar in..."
      : "Inspelningen är stoppad.";
  }

  togglePlayback() {
    if (!this.wavesurfer) return;

    if (this.wavesurfer.isPlaying()) {
      this.wavesurfer.pause();
    } else if (this.region) {
      this.wavesurfer.play(this.region.start, this.region.end);
    } else {
      this.wavesurfer.play();
    }
  }

  async handleRecordingStop(chunks) {
    try {
      // Confirm WaveSurfer and Regions are available
      if (!window.WaveSurfer || !window.WaveSurfer.regions) {
        console.error("WaveSurfer or Regions plugin not available");
        throw new Error("WaveSurfer or Regions plugin not available");
      }

      this.audioBlob = new Blob(chunks, { type: "audio/webm" });
      this.isRecording = false;

      const audioUrl = URL.createObjectURL(this.audioBlob);

      // Destroy existing WaveSurfer instance if it exists
      if (this.wavesurfer) {
        this.wavesurfer.destroy();
      }

      // Create WaveSurfer instance EXACTLY like the local example
      this.wavesurfer = WaveSurfer.create({
        waveColor: "#005a87",
        progressColor: "#1976d2",
        height: 100,
        interact: true,
        minPxPerSec: 50,
        container: "#waveform",
        plugins: [
          WaveSurfer.regions.create({
            dragSelection: false,
          }),
        ],
      });

      // Load audio and wait for it to be ready
      await new Promise((resolve, reject) => {
        // Mimic local example's region creation
        this.wavesurfer.on("ready", () => {
          const duration = this.wavesurfer.getDuration();

          // Use addRegion method directly
          this.region = this.wavesurfer.addRegion({
            start: 0,
            end: duration,
            drag: false,
            resize: true,
            color: "rgba(44, 202, 237, 0.2)",
          });

          resolve();
        });

        this.wavesurfer.on("error", (error) => {
          console.error("Wavesurfer error:", error);
          reject(error);
        });

        this.wavesurfer.load(audioUrl);
      });

      // Update UI
      this.updateUIForRecording(false);
      this.playBtn.disabled = false;
      this.trimBtn.disabled = false;
      this.uploadBtn.disabled = false; // Enable upload button here

      // Clean up URL
      URL.revokeObjectURL(audioUrl);
    } catch (error) {
      console.error("Detailed error handling recording stop:", error);
      this.status.textContent = "Ett fel uppstod vid inspelningsstopp.";
    }
  }

  async updateAudioWithTrimmed(trimmedBlob) {
    try {
      console.log("Updating audio with trimmed blob:", {
        type: trimmedBlob.type,
        size: trimmedBlob.size,
      });

      // Store current region bounds before loading new audio
      const previousStart = this.region ? this.region.start : 0;
      const previousEnd = this.region ? this.region.end : null;

      this.audioBlob = trimmedBlob;
      const audioUrl = URL.createObjectURL(trimmedBlob);

      // Destroy existing WaveSurfer instance
      if (this.wavesurfer) {
        this.wavesurfer.destroy();
      }

      // Recreate WaveSurfer with regions plugin
      this.wavesurfer = WaveSurfer.create({
        waveColor: "#005a87",
        progressColor: "#1976d2",
        height: 100,
        interact: true,
        minPxPerSec: 50,
        container: "#waveform",
        plugins: [
          WaveSurfer.regions.create({
            dragSelection: false,
          }),
        ],
      });

      // Load audio and create region
      await new Promise((resolve, reject) => {
        this.wavesurfer.on("ready", () => {
          const duration = this.wavesurfer.getDuration();

          // Create region with full or previous length
          this.region = this.wavesurfer.addRegion({
            start: 0,
            end: Math.min(previousEnd || duration, duration),
            drag: false,
            resize: true,
            color: "rgba(44, 202, 237, 0.2)",
          });

          resolve();
        });

        this.wavesurfer.on("error", reject);
        this.wavesurfer.load(audioUrl);
      });

      this.status.textContent = "Ljudet har trimmats.";
      this.uploadBtn.disabled = false;

      // Clean up URL
      URL.revokeObjectURL(audioUrl);
    } catch (error) {
      console.error("Ett fel uppstod vid uppdatering av trimningen:", error);
      throw error;
    }
  }

  async trimAudio() {
    console.log("Trim debug:", {
      wavesurfer: this.wavesurfer,
      regions: this.wavesurfer?.regions,
      regionsType: typeof this.wavesurfer?.regions,
      clearRegionsExists: typeof this.wavesurfer?.regions?.clearRegions,
    });

    if (!this.region || !this.audioBlob || !this.wavesurfer) {
      console.error("Cannot trim audio: Missing required components");
      return;
    }

    // Start loading animation
    this.showLoading('Trimmar ljudet...');

    try {
      const trimmedBlob = await this.createTrimmedAudio();
      if (trimmedBlob) {
        await this.updateAudioWithTrimmed(trimmedBlob);
      }
    } catch (error) {
      console.error("Error trimming audio:", error);
      this.status.textContent = "Ett fel uppstod vid trimning av ljudet.";
    } finally {
      // Stop the loading animation
      this.hideLoading();
    }
  }

  async createTrimmedAudio() {
    const audioContext = new (window.AudioContext ||
      window.webkitAudioContext)();
    const arrayBuffer = await this.audioBlob.arrayBuffer();
    const audioBuffer = await audioContext.decodeAudioData(arrayBuffer);

    const startSample = Math.floor(this.region.start * audioBuffer.sampleRate);
    const endSample = Math.floor(this.region.end * audioBuffer.sampleRate);
    const trimmedLength = endSample - startSample;

    const trimmedBuffer = audioContext.createBuffer(
      audioBuffer.numberOfChannels,
      trimmedLength,
      audioBuffer.sampleRate
    );

    for (let channel = 0; channel < audioBuffer.numberOfChannels; channel++) {
      const channelData = audioBuffer.getChannelData(channel);
      const trimmedData = trimmedBuffer.getChannelData(channel);
      for (let i = 0; i < trimmedLength; i++) {
        trimmedData[i] = channelData[startSample + i];
      }
    }

    const mediaStream = audioContext.createMediaStreamDestination();
    const source = audioContext.createBufferSource();
    source.buffer = trimmedBuffer;
    source.connect(mediaStream);
    source.start();

    return new Promise((resolve) => {
      const mediaRecorder = new MediaRecorder(mediaStream.stream, {
        mimeType: "audio/webm;codecs=opus",
      });
      const chunks = [];

      mediaRecorder.ondataavailable = (e) => chunks.push(e.data);
      mediaRecorder.onstop = () => {
        const blob = new Blob(chunks, { type: "audio/webm" });
        resolve(blob);
      };

      mediaRecorder.start();
      setTimeout(() => mediaRecorder.stop(), trimmedBuffer.duration * 1000);
    });
  }

  showLoading(message) {
    if (this.loadingOverlay && this.loadingMessageElement) {
      this.loadingMessageElement.textContent = message;
      this.loadingOverlay.style.display = "flex";
    }

    // Disable all action buttons to prevent interference
    if (this.startBtn) this.startBtn.disabled = true;
    if (this.stopBtn) this.stopBtn.disabled = true;
    if (this.playBtn) this.playBtn.disabled = true;
    if (this.trimBtn) this.trimBtn.disabled = true;
    if (this.uploadBtn) this.uploadBtn.disabled = true;
  }

  hideLoading() {
    if (this.loadingOverlay) {
      this.loadingOverlay.style.display = "none";
    }

    // Re-enable buttons based on the current state of the recorder
    this.startBtn.disabled = this.isRecording; // Can start if not currently recording (and passage selected)
    this.stopBtn.disabled = !this.isRecording;  // Can stop if currently recording

    if (this.audioBlob) { // If there's audio loaded
      this.playBtn.disabled = false;
      this.trimBtn.disabled = false;
      this.uploadBtn.disabled = false;
    } else { // No audio loaded
      this.playBtn.disabled = true;
      this.trimBtn.disabled = true;
      this.uploadBtn.disabled = true;
      // If no audio, but a passage is selected, re-enable start button
      if (document.getElementById("current-passage-id")?.value) {
        this.startBtn.disabled = false;
      } else {
        this.startBtn.disabled = true; // No passage selected
      }
    }
    // Special case: if recording just finished, stop button might have been re-enabled
    // but we're no longer recording, so ensure it's disabled if hideLoading is called after stop.
    if (!this.isRecording && this.stopBtn) {
      this.stopBtn.disabled = true;
    }
  }

  async uploadRecording() {
    if (!this.audioBlob) {
      this.status.textContent =
        "Ingen inspelning att ladda upp. Spela in först.";
      return;
    }

    // Start loading animation
    this.showLoading('Laddar upp inspelning...');

    try {
      const formData = new FormData();
      const currentPassageId =
        document.getElementById("current-passage-id")?.value;

      if (!currentPassageId) {
        throw new Error("Ingen text vald för uppladdning.");
      }

      formData.append("action", "ra_save_recording");
      formData.append("audio_file", this.audioBlob, "recording.webm");
      formData.append("passage_id", currentPassageId);
      formData.append("duration", this.wavesurfer.getDuration().toString());
      formData.append("nonce", raAjax.nonce);

      // Get user grade value
      const userGradeInput = document.getElementById('user-grade');
      if (userGradeInput) {
        formData.append("user_grade", userGradeInput.value.trim());
      }

      const response = await fetch(raAjax.ajax_url, {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      });

      const data = await response.json();

      if (data.success) {
        console.log("Upload response data:", data);
        this.status.textContent = "Inspelningen har laddats upp.";
        this.recordingId = data.data.recording_id;

        // Update nonce for subsequent requests
        if (data.data && data.data.new_nonce) {
          raAjax.nonce = data.data.new_nonce;
          console.log("New nonce received and updated:", raAjax.nonce);
        } else {
          console.warn("New nonce was not provided in the upload response.");
        }

        // Check if questions are optional
        if (data.data && data.data.questions_optional === true) {
          console.log("Questions are optional, skipping fetching questions.");
          this.status.textContent = "Inga frågor krävs.";
          // Potentially hide the questions section or enable a 'finish' button directly
          // this.passageSelector.disabled = false; // TODO: Ensure this.passageSelector is defined if used
          this.startBtn.disabled = false; // Corrected from this.recordButton
          // Reset UI for new recording if desired
        } else {
          // After successful upload, show questions (if not optional)
          const questions = await this.fetchQuestionsForPassage(currentPassageId);
          this.showQuestions(questions);
        }
      } else {
        throw new Error(data.data?.message || "Uppladdningen misslyckades");
      }
    } catch (error) {
      console.error("Upload failed:", error);
      this.status.textContent = `Ett fel uppstod vid uppladdningen: ${error.message}`;
    } finally {
      // Stop the loading animation
      this.hideLoading();
    }
  }

  writeString(view, offset, string) {
    for (let i = 0; i < string.length; i++) {
      view.setUint8(offset + i, string.charCodeAt(i));
    }
  }

  handlePassageChange(passageId) {
    this.cleanupRecording();
    this.updateUIForPassage(passageId);
  }

  cleanupRecording() {
    if (this.mediaStream) {
      this.mediaStream.getTracks().forEach((track) => track.stop());
      this.mediaStream = null;
    }
    if (this.recorder?.state === "recording") {
      this.recorder.stop();
    }
    if (this.wavesurfer) {
      this.wavesurfer.destroy();
      this.wavesurfer = null;
    }

    this.recorder = null;
    this.audioBlob = null;
    this.region = null;
    this.isRecording = false;

    this.initializeButtonStates();
  }

  updateUIForPassage(passageId) {
    const hasValidPassage = passageId && passageId !== "0";
    this.startBtn.disabled = !hasValidPassage;
    this.stopBtn.disabled = true;
    this.status.textContent = hasValidPassage
      ? "Klicka på 'Spela in' för att börja."
      : "Välj en text innan du börjar spela in.";
  }

  async fetchQuestionsForPassage(passageId) {
    try {
      const formData = new FormData();
      formData.append("action", "ra_public_get_questions");
      formData.append("passage_id", passageId);
      formData.append("nonce", raAjax.nonce);

      const response = await fetch(raAjax.ajax_url, {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      });

      const data = await response.json();

      if (data.success && data.data) {
        return data.data;
      } else {
        throw new Error(data.data?.message || "Kunde inte hämta frågorna");
      }
    } catch (error) {
      console.error("Error fetching questions:", error);
      throw error;
    }
  }

  showQuestions(questionsData) {
    // Create the questions form
    let questionsHtml = '<form id="questions-form" class="ra-questions-form">';
    questionsData.forEach((question) => {
      questionsHtml += `
        <div class="ra-question-item">
          <label for="question-${question.id}">
            ${question.question_text}
          </label>
          <input type="text"
            id="question-${question.id}"
            name="answers[${question.id}]"
            class="ra-answer-input"
            required>
        </div>
      `;
    });
    questionsHtml += `
      <button type="submit" class="ra-button submit-answers">
        Skicka svar
      </button>
    </form>`;

    // Create or update questions section
    let questionsSection = document.getElementById("questions-section");
    if (!questionsSection) {
      questionsSection = document.createElement("div");
      questionsSection.id = "questions-section";
      document.body.appendChild(questionsSection);
    }

    // Insert questions HTML
    questionsSection.innerHTML = questionsHtml;
    questionsSection.style.display = "block";

    // Add event listener for form submission
    const questionsForm = document.getElementById("questions-form");
    questionsForm.addEventListener(
      "submit",
      this.handleQuestionSubmit.bind(this)
    );
  }

  async handleQuestionSubmit(event) {
    event.preventDefault();

    console.log("Submitting answers with recording ID:", this.recordingId);
    console.log("Current User logged in status:", !!raAjax.current_user_id);

    if (!this.recordingId) {
      this.status.textContent =
        "Ingen inspelning hittades. Försök ladda upp igen.";
      return;
    }

    const form = event.target;
    const submitButton = form.querySelector('button[type="submit"]');
    submitButton.disabled = true;

    try {
      const formData = new FormData(form);
      const answers = {};

      for (let [key, value] of formData.entries()) {
        if (key.startsWith("answers[") && key.endsWith("]")) {
          const questionId = key.match(/\[(\d+)\]/)[1];
          answers[questionId] = value;
        }
      }

      const submissionData = new FormData();
      submissionData.append("action", "ra_submit_answers");
      submissionData.append("nonce", raAjax.nonce);
      submissionData.append("recording_id", this.recordingId.toString());
      submissionData.append("answers", JSON.stringify(answers));

      console.log("Submission Data:", {
        action: "ra_submit_answers",
        nonce: !!raAjax.nonce,
        recording_id: this.recordingId.toString(),
        answers_count: Object.keys(answers).length,
      });

      const response = await fetch(raAjax.ajax_url, {
        method: "POST",
        body: submissionData,
        credentials: "same-origin",
      });

      const data = await response.json();

      console.log("Server Response:", data);

      if (data.success) {
        this.status.textContent = "Dina svar har sparats.";
        form.style.display = "none";
      } else {
        throw new Error(
          data.data?.message || "Kunde inte skicka in dina svar."
        );
      }
    } catch (error) {
      console.error("Error submitting answers:", error);
      this.status.textContent = `Ett fel uppstod vid inskickande av svaren: ${error.message}`;
      submitButton.disabled = false;
    }
  }
  // End class and leave this f**ing line alone!
}

// Recorder Manager to handle initialization
window.RecorderManager = {
  instance: null,
  isInitialized: false,

  async initialize(container) {
    if (!this.instance) {
      this.instance = new ReadingAssessmentRecorder(container);
      this.isInitialized = true;
    }
    return this.instance;
  },

  getInstance() {
    return this.instance;
  },

  isReady() {
    return this.isInitialized && this.instance !== null;
  },
};

// Modified initialization code
document.addEventListener("DOMContentLoaded", async function () {
  const container = document.querySelector(".ra-audio-recorder");
  console.log("Recorder container found:", !!container);

  if (container) {
    try {
      await window.RecorderManager.initialize(container);
    } catch (error) {
      console.error("Error initializing RecorderManager:", error);
    }
  } else {
    console.error("Recorder container not found in DOM");
  }
});
