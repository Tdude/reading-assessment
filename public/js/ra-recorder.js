// ra-recorder.js
window.addEventListener("unhandledrejection", function (event) {
  // Prevent jQuery Migrate's null Promise rejection from showing as an error
  if (event.reason === null) {
    event.preventDefault();
  }
});
// 1. First, define the RecorderManager
window.RecorderManager = {
  instance: null,
  isInitialized: false,
  initializationPromise: null,

  async initialize(container) {
    if (this.initializationPromise) {
      return this.initializationPromise;
    }

    this.initializationPromise = new Promise(async (resolve) => {
      try {
        console.log("Starting recorder initialization");
        await waitForWaveSurfer();

        if (!this.instance && container) {
          this.instance = new ReadingAssessmentRecorder(container);
          this.isInitialized = true;
          console.log("Recorder initialized successfully");
        }
        resolve(this.instance);
      } catch (error) {
        console.error("Error initializing recorder:", error);
        this.isInitialized = false;
        this.initializationPromise = null;
        resolve(null);
      }
    });

    return this.initializationPromise;
  },

  getInstance() {
    return this.instance;
  },

  isReady() {
    return this.isInitialized && this.instance !== null;
  },
};

// 2. Then, the WaveSurfer check function
function waitForWaveSurfer() {
  return new Promise((resolve) => {
    if (window.WaveSurfer && window.WaveSurfer.Regions) {
      console.log("WaveSurfer already available");
      resolve();
    } else {
      console.log("Waiting for WaveSurfer...");
      const checkInterval = setInterval(() => {
        if (window.WaveSurfer && window.WaveSurfer.Regions) {
          console.log("WaveSurfer now available");
          clearInterval(checkInterval);
          resolve();
        }
      }, 100);
    }
  });
}

// 3. The initialization function
async function initializeRecorder() {
  console.log("Starting recorder initialization process");
  const container = document.querySelector(".ra-audio-recorder");
  if (container) {
    await window.RecorderManager.initialize(container);
    // Dispatch event when recorder is ready
    window.dispatchEvent(new CustomEvent("recorderReady"));
  } else {
    console.error("Recorder container not found");
  }
}

// 4. The class definition
class ReadingAssessmentRecorder {
  constructor(container) {
    if (!container) {
      console.error("Container is required for ReadingAssessmentRecorder");
      return;
    }

    this.container = container;
    this.mediaStream = null;
    this.recorder = null;
    this.audioBlob = null;
    this.wavesurfer = null;
    this.regionsPlugin = null;
    this.isRecording = false;

    // Get UI elements
    this.startBtn = container.querySelector("#start-recording");
    this.stopBtn = container.querySelector("#stop-recording");
    this.playBtn = container.querySelector("#playback");
    this.trimBtn = container.querySelector("#trim-audio");
    this.uploadBtn = container.querySelector("#upload-recording");
    this.status = container.querySelector("#status");
    this.waveformContainer = container.querySelector("#waveform");

    if (!this.validateElements()) {
      console.error("Required UI elements not found");
      return;
    }

    this.initializeButtonStates();
    this.bindEventHandlers();
    this.checkInitialPassage();
  }

  validateElements() {
    return (
      this.startBtn &&
      this.stopBtn &&
      this.playBtn &&
      this.trimBtn &&
      this.uploadBtn &&
      this.status &&
      this.waveformContainer
    );
  }

  initializeButtonStates() {
    this.startBtn.disabled = false;
    this.stopBtn.disabled = true;
    this.playBtn.disabled = true;
    this.trimBtn.disabled = true;
    this.uploadBtn.disabled = true;
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
    console.log("Checking initial passage ID:", currentPassageId);

    if (currentPassageId) {
      this.handlePassageChange(currentPassageId);
    } else {
      // If no passage ID, disable start button and show message
      this.startBtn.disabled = true;
      this.status.textContent = "Välj en text innan du börjar spela in.";
    }
  }

  initializeWaveSurfer() {
    try {
      console.log("Initializing WaveSurfer...");
      if (this.wavesurfer) {
        this.wavesurfer.destroy();
      }

      // Create bare WaveSurfer first
      this.wavesurfer = WaveSurfer.create({
        container: this.waveformContainer,
        waveColor: "#005a87",
        progressColor: "#1976d2",
        height: 100,
        interact: true,
        minPxPerSec: 50,
      });

      // Create Regions plugin exactly like the example
      this.regionsPlugin = WaveSurfer.Regions.create();
      this.wavesurfer.registerPlugin(this.regionsPlugin);

      console.log("Plugin registration:", {
        wavesurfer: this.wavesurfer,
        regions: this.regionsPlugin,
        container: this.waveformContainer,
      });

      this.setupWavesurferEvents();
      return this.wavesurfer;
    } catch (error) {
      console.error("Error initializing WaveSurfer:", error);
      throw error;
    }
  }

  createRegion(start, end) {
    if (!this.regionsPlugin) return null;

    this.regionsPlugin.clearRegions();
    return this.regionsPlugin.addRegion({
      start,
      end,
      resize: true,
      color: "rgba(44, 202, 237, 0.2)",
    });
  }

  setupWavesurferEvents() {
    if (!this.wavesurfer) return;

    this.wavesurfer.on("decode", () => {
      const duration = this.wavesurfer.getDuration();
      this.region = this.createRegion(0, duration);

      console.log("Region creation result:", {
        region: this.region,
        parent: this.region.element?.parentElement,
        parentStyle: this.region.element?.parentElement?.getAttribute("style"),
      });
    });

    // Existing listeners
    this.regionsPlugin.on("region-updated", (region) => {
      console.log("Region being updated:", region);
    });

    this.regionsPlugin.on("region-update-end", (region) => {
      console.log("Region update finished:", region);
    });

    // Add these new debug listeners
    this.regionsPlugin.on("region-clicked", (region, e) => {
      console.log("Region clicked:", { region, event: e });
    });

    this.regionsPlugin.on("region-mouseover", (region) => {
      console.log("Region mouse over:", region);
    });

    this.regionsPlugin.on("region-mouseout", (region) => {
      console.log("Region mouse out:", region);
    });
  }

  async handleWavesurferReady() {
    if (!this.isRecording) {
      const duration = this.wavesurfer.getDuration();
      await this.createRegion(0, duration);

      // Enable playback controls
      this.playBtn.disabled = false;
      this.trimBtn.disabled = false;

      // Update UI state
      this.updateUIForRecording(false);
    }
  }

  updatePlayButtonState(isPlaying) {
    const label = this.playBtn.querySelector(".ra-label");
    const icon = this.playBtn.querySelector(".ra-icon");

    if (isPlaying) {
      label.textContent = "Paus";
      icon.textContent = "⏸";
    } else {
      label.textContent = "Spela";
      icon.textContent = "▶";
    }
  }

  async startRecording() {
    try {
      this.mediaStream = await navigator.mediaDevices.getUserMedia({
        audio: true,
      });
      this.recorder = new MediaRecorder(this.mediaStream);
      this.isRecording = true;

      const chunks = [];
      this.recorder.ondataavailable = (e) => chunks.push(e.data);
      this.recorder.onstop = async () => this.handleRecordingStop(chunks);

      this.recorder.start();
      this.updateUIForRecording(true);
    } catch (error) {
      console.error("Recording failed:", error);
      alert(
        "Kunde inte starta inspelningen. Kontrollera att mikrofonen är aktiverad."
      );
    }
  }

  async handleRecordingStop(chunks) {
    try {
      this.audioBlob = new Blob(chunks, { type: "audio/webm" });
      this.isRecording = false;

      console.log("Recording stopped, blob created:", {
        type: this.audioBlob.type,
        size: this.audioBlob.size,
      });

      const audioUrl = URL.createObjectURL(this.audioBlob);

      // Initialize WaveSurfer and load audio
      if (!this.wavesurfer) {
        await this.initializeWaveSurfer();
      } else {
        // Just clear existing regions
        this.regionsPlugin.clearRegions();
      }
      await this.wavesurfer.load(audioUrl);

      // Region will be created automatically on 'decode' event

      // Update UI state
      this.updateUIForRecording(false);
      this.playBtn.disabled = false;
      this.trimBtn.disabled = false;

      // Clean up URL
      URL.revokeObjectURL(audioUrl);
    } catch (error) {
      console.error("Error handling recording stop:", error);
      this.status.textContent = "Ett fel uppstod vid inspelningsstopp.";
    }
  }

  updateUIForRecording(isRecording) {
    this.startBtn.disabled = isRecording;
    this.stopBtn.disabled = !isRecording;
    this.status.textContent = isRecording
      ? "Spelar in..."
      : "Inspelningen är stoppad.";
  }

  stopRecording() {
    if (this.recorder?.state === "recording") {
      this.recorder.stop();
      this.mediaStream.getTracks().forEach((track) => track.stop());
    }
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

  async trimAudio() {
    console.log("Trim components check:", {
      hasRegion: !!this.region,
      hasAudioBlob: !!this.audioBlob,
      hasWavesurfer: !!this.wavesurfer,
      regionDetails: this.region,
      wavesurferState: this.wavesurfer?.isReady,
    });

    if (!this.region || !this.audioBlob || !this.wavesurfer) {
      console.error("Cannot trim audio: Missing required components");
      return;
    }

    try {
      const duration = this.wavesurfer.getDuration();
      const regionStart = parseFloat(this.region.start);
      const regionEnd = parseFloat(this.region.end);

      console.log("Current region bounds:", {
        start: regionStart,
        end: regionEnd,
        duration: duration,
      });

      // Remove the check for "no trimming needed"
      const trimmedBlob = await this.createTrimmedAudio();
      if (trimmedBlob) {
        await this.updateAudioWithTrimmed(trimmedBlob);
        console.log("Audio trimmed successfully");
      }
    } catch (error) {
      console.error("Error trimming audio:", error);
      this.status.textContent = "Ett fel uppstod vid trimning av ljudet.";
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

    // Create new buffer for the trimmed audio
    const trimmedBuffer = audioContext.createBuffer(
      audioBuffer.numberOfChannels,
      trimmedLength,
      audioBuffer.sampleRate
    );

    // Copy the selected portion
    for (let channel = 0; channel < audioBuffer.numberOfChannels; channel++) {
      const channelData = audioBuffer.getChannelData(channel);
      const trimmedData = trimmedBuffer.getChannelData(channel);
      for (let i = 0; i < trimmedLength; i++) {
        trimmedData[i] = channelData[startSample + i];
      }
    }

    // Convert to a MediaStream and then to a WebM blob
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

  async uploadRecording() {
    if (!this.audioBlob) return;

    try {
      const formData = new FormData();
      const currentPassageId =
        document.getElementById("current-passage-id")?.value;

      console.log("Audio blob before upload:", {
        type: this.audioBlob.type,
        size: this.audioBlob.size,
        content: await this.audioBlob
          .text()
          .catch((e) => "Unable to read blob content"),
      });

      formData.append("action", "ra_save_recording");
      formData.append("audio_file", this.audioBlob, "recording.webm");
      formData.append("passage_id", currentPassageId);
      formData.append("duration", this.wavesurfer.getDuration().toString());
      formData.append("nonce", raAjax.nonce);

      // Log FormData contents
      console.log("FormData contents:");
      for (let pair of formData.entries()) {
        if (pair[0] === "audio_file") {
          console.log("audio_file:", {
            name: pair[1].name,
            type: pair[1].type,
            size: pair[1].size,
          });
        } else {
          console.log(pair[0], pair[1]);
        }
      }

      const response = await fetch(raAjax.ajax_url, {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      });

      const data = await response.json();

      if (data.success) {
        this.status.textContent = "Inspelningen har laddats upp.";
        const questionsSection = document.getElementById("questions-section");
        if (questionsSection) {
          questionsSection.style.display = "block";
        }
      } else {
        console.error("Server response:", data);
        throw new Error(data.data?.message || "Uppladdningen misslyckades");
      }
    } catch (error) {
      console.error("Upload failed:", error);
      this.status.textContent =
        "Ett fel uppstod vid uppladdningen: " + (error.message || "Okänt fel");
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

      // Initialize new WaveSurfer instance
      await this.initializeWaveSurfer();
      await this.wavesurfer.load(audioUrl);

      // After loading, recreate region with previous end position if it exists
      if (previousEnd !== null) {
        const duration = this.wavesurfer.getDuration();
        this.createRegion(previousStart, Math.min(previousEnd, duration));
      }

      this.status.textContent = "Ljudet har trimmats.";
      this.uploadBtn.disabled = false;

      // Clean up URL
      URL.revokeObjectURL(audioUrl);
    } catch (error) {
      console.error("Error updating trimmed audio:", error);
      throw error;
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
    this.hasRegion = false;

    this.initializeButtonStates();
    this.updatePlayButtonState(false);
  }

  updateUIForPassage(passageId) {
    console.log("Updating UI for passage:", passageId);
    const hasValidPassage = passageId && passageId !== "0";
    this.startBtn.disabled = !hasValidPassage;
    this.stopBtn.disabled = true;
    this.status.textContent = hasValidPassage
      ? "Klicka på 'Spela in' för att börja."
      : "Välj en text innan du börjar spela in.";
  }

  async loadAudioBlob(blob) {
    if (this.wavesurfer) {
      const audioUrl = URL.createObjectURL(blob);
      await this.wavesurfer.load(audioUrl);
      this.audioBlob = blob;
    }
  }

  async audioBufferToBlob(audioBuffer) {
    const wav = this.audioBufferToWav(audioBuffer);
    return new Blob([wav], { type: "audio/wav" });
  }

  audioBufferToWav(buffer) {
    const numChannels = buffer.numberOfChannels;
    const length = buffer.length;
    const sampleRate = buffer.sampleRate;
    const bytesPerSample = 2;
    const blockAlign = numChannels * bytesPerSample;
    const byteRate = sampleRate * blockAlign;
    const dataSize = length * blockAlign;

    const bufferArray = new ArrayBuffer(44 + dataSize);
    const view = new DataView(bufferArray);

    // Write WAV header
    this.writeString(view, 0, "RIFF"); // RIFF header
    view.setUint32(4, 36 + dataSize, true); // File size
    this.writeString(view, 8, "WAVE"); // WAVE format
    this.writeString(view, 12, "fmt "); // fmt chunk
    view.setUint32(16, 16, true); // fmt chunk size
    view.setUint16(20, 1, true); // Audio format (1 = PCM)
    view.setUint16(22, numChannels, true); // Number of channels
    view.setUint32(24, sampleRate, true); // Sample rate
    view.setUint32(28, byteRate, true); // Byte rate
    view.setUint16(32, blockAlign, true); // Block align
    view.setUint16(34, bytesPerSample * 8, true); // Bits per sample
    this.writeString(view, 36, "data"); // data chunk
    view.setUint32(40, dataSize, true); // data chunk size

    // Write PCM audio data
    let offset = 44;
    for (let i = 0; i < numChannels; i++) {
      const channelData = buffer.getChannelData(i);
      for (let j = 0; j < channelData.length; j++) {
        const sample = Math.max(-1, Math.min(1, channelData[j])); // Clamp sample to [-1, 1]
        view.setInt16(
          offset,
          sample < 0 ? sample * 0x8000 : sample * 0x7fff,
          true
        ); // Convert to 16-bit PCM
        offset += 2;
      }
    }

    return bufferArray;
  }

  writeString(view, offset, string) {
    for (let i = 0; i < string.length; i++) {
      view.setUint8(offset + i, string.charCodeAt(i));
    }
  }
}

// 5. Export class
window.ReadingAssessmentRecorder = ReadingAssessmentRecorder;

// Initialize when DOM is ready
document.addEventListener("DOMContentLoaded", async function () {
  await waitForWaveSurfer();
  const container = document.querySelector(".ra-audio-recorder");
  if (container) {
    window.RecorderManager.initialize(container);
  }
});
