let recorder, audioBlob, wavesurfer, mediaStream;

document.addEventListener("DOMContentLoaded", () => {
  const startBtn = document.getElementById("start-recording");
  const stopBtn = document.getElementById("stop-recording");
  const uploadBtn = document.getElementById("upload-recording");
  const playbackBtn = document.getElementById("playback");
  const trimBtn = document.getElementById("trim-audio");
  const status = document.getElementById("status");

  // Check for WaveSurfer
  if (!window.WaveSurfer) {
    console.error("WaveSurfer is not loaded.");
    return;
  }

  // Initialize WaveSurfer with RegionsPlugin
  wavesurfer = WaveSurfer.create({
    container: "#waveform",
    waveColor: "violet",
    progressColor: "purple",
    cursorColor: "red",
  });

  // Create and add regions plugin
  const regionsPlugin = wavesurfer.registerPlugin(
    WaveSurfer.Regions.create({
      dragSelection: {
        slop: 5,
      },
    })
  );

  console.log("WaveSurfer initialized with regions plugin");

  // Handle MediaRecorder
  startBtn.addEventListener("click", async () => {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      alert("Det verkar som din webbläsare inte stöder inspelning tyvärr.");
      return;
    }
    mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
    const audioContext = new AudioContext();
    const input = audioContext.createMediaStreamSource(mediaStream);

    recorder = new MediaRecorder(mediaStream, { mimeType: "audio/webm" });
    const dataChunks = [];

    recorder.ondataavailable = (e) => {
      console.log("Data chunk received: ", e.data);
      dataChunks.push(e.data);
    };

    recorder.onstop = () => {
      console.log("All data chunks: ", dataChunks);

      if (dataChunks.length === 0) {
        console.error("No audio data captured.");
        status.textContent = "Recording failed: No audio data.";
        return;
      }

      audioBlob = new Blob(dataChunks, { type: "audio/webm" });

      if (audioBlob) {
        console.log("Blob size: ", audioBlob.size);
        console.log("Blob type: ", audioBlob.type);

        const debugLink = document.createElement("a");
        debugLink.href = URL.createObjectURL(audioBlob);
        debugLink.download = "debug.webm";
        debugLink.textContent = "Download Debug Audio";
        document.body.appendChild(debugLink);
      }

      const audioUrl = URL.createObjectURL(audioBlob);

      const audioElement = document.createElement("audio");
      audioElement.src = audioUrl;
      audioElement.controls = true;
      document.body.appendChild(audioElement);

      wavesurfer.load(audioUrl);

      status.textContent =
        "Inspelningen är stoppad. Du kan nu spela upp, trimma eller ladda upp den.";
      stopBtn.disabled = true;
      uploadBtn.disabled = false;
      playbackBtn.disabled = false;
      trimBtn.disabled = false;
    };

    recorder.start();
    status.textContent = "Spelar in...";
    startBtn.disabled = true;
    stopBtn.disabled = false;
  });

  stopBtn.addEventListener("click", () => {
    if (recorder) {
      recorder.stop();
      mediaStream.getTracks().forEach((track) => track.stop());
      startBtn.disabled = false;
      stopBtn.disabled = true;
    }
  });

  uploadBtn.addEventListener("click", () => {
    if (!audioBlob) return;

    // Disable the button immediately
    uploadBtn.disabled = true;
    // Optionally change text to show it's processing
    uploadBtn.textContent = "Laddar upp...";

    const formData = new FormData();
    formData.append("action", "ra_save_recording");
    formData.append("audio_file", audioBlob, "recording.webm");

    fetch(raAjax.ajax_url, {
      method: "POST",
      body: formData,
      credentials: "same-origin",
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          status.textContent = "Ljudfilen sparades. Hör och häpna!";
          // Optionally disable other controls after successful upload
          stopBtn.disabled = true;
          startBtn.disabled = false;
        } else {
          status.textContent =
            "Misslyckades att ladda upp: " +
            (data.data?.message || "Unknown error");
          // Re-enable on failure
          uploadBtn.disabled = false;
          uploadBtn.textContent = "Ladda upp";
        }
      })
      .catch((error) => {
        console.error("Upload error:", error);
        status.textContent =
          "Det uppstod ett fel vid uppladdningen: " + error.message;
        // Re-enable on error
        uploadBtn.disabled = false;
        uploadBtn.textContent = "Ladda upp";
      });
  });

  playbackBtn.addEventListener("click", () => {
    if (wavesurfer.isPlaying()) {
      wavesurfer.pause();
      playbackBtn.textContent = "Spela upp";
    } else {
      wavesurfer.play();
      playbackBtn.textContent = "Pausa inspelning";
    }
  });

  trimBtn.addEventListener("click", () => {
    try {
      const regions = regionsPlugin.getRegions();
      if (regions.length === 0) {
        // If no region exists, create one for the entire track
        const duration = wavesurfer.getDuration();
        regionsPlugin.addRegion({
          start: 0,
          end: duration,
          color: "rgba(180, 243, 200, 0.5)",
        });
        status.textContent =
          "Det blev ingen trimning. Försök trimma längden igen.";
        return;
      }

      const region = regions[0];
      const { start, end } = region;
      status.textContent = `Trimmat ljud från ${start.toFixed(
        2
      )}s till ${end.toFixed(2)}s.`;
    } catch (err) {
      console.error("Error handling regions:", err);
      status.textContent = "Det blev ett fel när jag skulle trimma ljudet";
    }
  });

  wavesurfer.on("ready", () => {
    try {
      // Create initial region covering the entire track
      const duration = wavesurfer.getDuration();
      regionsPlugin.addRegion({
        start: 0,
        end: duration,
        color: "rgba(190, 250, 210, 0.5)",
      });
    } catch (err) {
      console.error("Error creating initial region:", err);
    }
  });

  // Keyboard Controls
  document.addEventListener("keydown", (event) => {
    if (event.code === "Space") {
      event.preventDefault();
      if (!recorder || recorder.state === "inactive") {
        startBtn.click();
      } else if (recorder.state === "recording") {
        stopBtn.click();
      } else if (wavesurfer) {
        playbackBtn.click();
      }
    }
  });
});
