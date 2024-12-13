let recorder, audioBlob, wavesurfer, mediaStream;

document.addEventListener("DOMContentLoaded", () => {
  const startBtn = document.getElementById("start-recording");
  const stopBtn = document.getElementById("stop-recording");
  const uploadBtn = document.getElementById("upload-recording");
  const playbackBtn = document.getElementById("playback");
  const trimBtn = document.getElementById("trim-audio");
  const status = document.getElementById("status");

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

  // Function to trim audio
  async function trimAudio(audioBlob, start, end) {
    const audioContext = new AudioContext();
    const audioBuffer = await new Response(audioBlob)
      .arrayBuffer()
      .then((arrayBuffer) => audioContext.decodeAudioData(arrayBuffer));

    // Calculate start and end samples
    const startSample = Math.floor(start * audioBuffer.sampleRate);
    const endSample = Math.floor(end * audioBuffer.sampleRate);
    const frameCount = endSample - startSample;

    // Create new buffer for trimmed audio
    const trimmedBuffer = audioContext.createBuffer(
      audioBuffer.numberOfChannels,
      frameCount,
      audioBuffer.sampleRate
    );

    // Copy the trimmed portion
    for (let channel = 0; channel < audioBuffer.numberOfChannels; channel++) {
      const channelData = audioBuffer.getChannelData(channel);
      const trimmedData = trimmedBuffer.getChannelData(channel);
      for (let i = 0; i < frameCount; i++) {
        trimmedData[i] = channelData[startSample + i];
      }
    }

    // Convert trimmed buffer back to blob
    const mediaStreamDest = audioContext.createMediaStreamDestination();
    const sourceNode = audioContext.createBufferSource();
    sourceNode.buffer = trimmedBuffer;
    sourceNode.connect(mediaStreamDest);
    sourceNode.start();

    const mediaRecorder = new MediaRecorder(mediaStreamDest.stream);
    const chunks = [];

    return new Promise((resolve) => {
      mediaRecorder.ondataavailable = (e) => chunks.push(e.data);
      mediaRecorder.onstop = () => {
        const trimmedBlob = new Blob(chunks, { type: "audio/webm" });
        resolve(trimmedBlob);
      };
      mediaRecorder.start();
      setTimeout(() => mediaRecorder.stop(), (end - start) * 1000 + 100);
    });
  }

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

    recorder.ondataavailable = (e) => dataChunks.push(e.data);

    recorder.onstop = () => {
      audioBlob = new Blob(dataChunks, { type: "audio/webm" });
      const audioUrl = URL.createObjectURL(audioBlob);
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

  uploadBtn.addEventListener("click", async () => {
    if (!audioBlob) return;

    uploadBtn.disabled = true;
    uploadBtn.textContent = "Laddar upp...";

    try {
      // Get the current region
      const regions = regionsPlugin.getRegions();
      let blobToUpload = audioBlob;
      let duration = wavesurfer.getDuration(); // Get the duration

      if (regions.length > 0) {
        const region = regions[0];
        // Only trim if the region doesn't cover the entire audio
        if (region.start > 0 || region.end < wavesurfer.getDuration()) {
          blobToUpload = await trimAudio(audioBlob, region.start, region.end);
          duration = region.end - region.start; // Update duration for trimmed audio
        }
      }

      const formData = new FormData();
      formData.append("action", "ra_save_recording");
      formData.append("audio_file", blobToUpload, "recording.webm");
      formData.append("duration", duration); // Add duration to form data

      const response = await fetch(raAjax.ajax_url, {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      });

      const data = await response.json();

      if (data.success) {
        status.textContent = "Ljudfilen sparades. Hör och häpna!";
        stopBtn.disabled = true;
        startBtn.disabled = false;
      } else {
        throw new Error(data.data?.message || "Unknown error");
      }
    } catch (error) {
      console.error("Upload error:", error);
      status.textContent =
        "Det uppstod ett fel vid uppladdningen: " + error.message;
      uploadBtn.disabled = false;
      uploadBtn.textContent = "Ladda upp";
    }
  });

  // Rest of the event listeners remain the same...
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
