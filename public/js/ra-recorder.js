document.addEventListener("DOMContentLoaded", () => {
  // Since we might have multiple recorders, we'll initialize each one
  document
    .querySelectorAll(".ra-audio-recorder")
    .forEach((recorderContainer) => {
      initializeRecorder(recorderContainer);
    });
});

function initializeRecorder(container) {
  // Get elements within this specific recorder container
  const startBtn = document.getElementById("start-recording");
  const stopBtn = document.getElementById("stop-recording");
  const uploadBtn = document.getElementById("upload-recording");
  const playbackBtn = document.getElementById("playback");
  const trimBtn = document.getElementById("trim-audio");
  const status = document.getElementById("status");
  const questionsSection = document.getElementById("questions-section");

  // Initialize WaveSurfer with RegionsPlugin
  const regions = WaveSurfer.Regions.create({
    dragSelection: {
      slop: 5,
    },
  });

  const wavesurfer = WaveSurfer.create({
    container: "#waveform",
    waveColor: "violet",
    progressColor: "purple",
    cursorColor: "red",
    plugins: [regions],
  });

  let recorder;
  let mediaStream;
  let audioBlob;

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

    wavesurfer.empty();
    regions.clearRegions();

    try {
      mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const audioContext = new AudioContext();
      const input = audioContext.createMediaStreamSource(mediaStream);

      recorder = new MediaRecorder(mediaStream, { mimeType: "audio/webm" });
      const dataChunks = [];

      recorder.ondataavailable = (e) => dataChunks.push(e.data);

      recorder.onstop = () => {
        audioBlob = new Blob(dataChunks, { type: "audio/webm" });
        const audioUrl = URL.createObjectURL(audioBlob);

        regions.clearRegions();
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
    } catch (err) {
      console.error("Recording error:", err);
      alert(
        "Kunde inte starta inspelningen. Kontrollera att mikrofonen är ansluten."
      );
    }
  });

  stopBtn.addEventListener("click", () => {
    if (recorder && recorder.state === "recording") {
      recorder.stop();
      mediaStream.getTracks().forEach((track) => track.stop());
      startBtn.disabled = false;
      stopBtn.disabled = true;
    }
  });

  uploadBtn.addEventListener("click", async () => {
    if (!audioBlob) return;

    const passageId = document.getElementById("current-passage-id").value;
    if (!passageId) {
      alert("Välj en text att läsa först!");
      return;
    }

    console.log("Uploading for passage ID:", passageId);

    uploadBtn.disabled = true;
    uploadBtn.textContent = "Laddar upp...";

    try {
      // --- First AJAX Call: Save Recording ---
      const currentRegions = regions.getRegions();
      let blobToUpload = audioBlob;
      let duration = wavesurfer.getDuration();

      if (currentRegions.length > 0) {
        const region = currentRegions[0];
        if (region.start > 0 || region.end < wavesurfer.getDuration()) {
          blobToUpload = await trimAudio(audioBlob, region.start, region.end);
          duration = region.end - region.start;
        }
      }

      const uploadFormData = new FormData();
      uploadFormData.append("action", "ra_save_recording");
      uploadFormData.append("audio_file", blobToUpload, "recording.webm");
      uploadFormData.append("duration", duration);
      uploadFormData.append("passage_id", passageId);

      console.log("Uploading recording...");
      const uploadResponse = await fetch(raAjax.ajax_url, {
        method: "POST",
        body: uploadFormData,
        credentials: "same-origin",
      });

      const uploadData = await uploadResponse.json();

      if (!uploadData.success) {
        throw new Error(
          uploadData.data?.message || "Kunde inte spara ljudfilen."
        );
      }

      console.log("Recording saved successfully:", uploadData);
      status.textContent = "Ljudfilen sparades. Nu kommer frågorna!";
      stopBtn.disabled = true;
      startBtn.disabled = false;
      uploadBtn.disabled = true;

      const recordingId = uploadData.data.recording_id;

      // --- Second AJAX Call: Get Questions ---
      const questionsFormData = new FormData();
      questionsFormData.append("action", "ra_public_get_questions"); // Updated action name
      questionsFormData.append("passage_id", passageId);
      questionsFormData.append("nonce", raAjax.nonce);

      console.log("Fetching questions...", {
        action: "ra_public_get_questions",
        passage_id: passageId,
        nonce: raAjax.nonce,
      });

      console.log("Fetching questions for passage:", passageId);
      const questionsResponse = await fetch(raAjax.ajax_url, {
        method: "POST",
        body: questionsFormData,
        credentials: "same-origin",
      });

      const rawResponse = await questionsResponse.clone().text();
      console.log("Raw response:", rawResponse);

      const questionsData = await questionsResponse.json();
      console.log("Questions data:", questionsData);

      if (!questionsData.success) {
        throw new Error(
          questionsData.data?.message || "Failed to load questions"
        );
      }

      const questions = questionsData.data;
      console.log("Processing questions:", questions);

      // Store recording ID
      questionsSection.dataset.recordingId = recordingId;

      // Build questions HTML
      let questionsHtml =
        '<form id="questions-form" class="ra-questions-form">';
      questions.forEach((question) => {
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

      questionsSection.innerHTML = `
          <h3>Frågor om texten</h3>
          ${questionsHtml}
      `;

      const questionsForm = questionsSection.querySelector("form");
      if (questionsForm) {
        questionsForm.addEventListener("submit", handleQuestionSubmit);
      }

      questionsSection.style.display = "block";
      status.textContent = "Svara på frågorna och skicka in dina svar.";
    } catch (error) {
      console.error("Error during questions fetch:", error);
      status.textContent = error.message;
      uploadBtn.disabled = false;
      uploadBtn.textContent = "Ladda upp";
    }
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
      const currentRegions = regions.getRegions();
      if (currentRegions.length === 0) {
        const duration = wavesurfer.getDuration();
        regions.addRegion({
          start: 0,
          end: duration,
          color: "rgba(180, 243, 200, 0.5)",
        });
      }
      const region = regions.getRegions()[0];
      status.textContent = `Trimmat ljud från ${region.start.toFixed(
        2
      )}s till ${region.end.toFixed(2)}s.`;
    } catch (err) {
      console.error("Error handling regions:", err);
      status.textContent = "Det blev ett fel när jag skulle trimma ljudet";
    }
  });

  wavesurfer.on("ready", () => {
    try {
      regions.clearRegions();
      regions.addRegion({
        start: 0,
        end: wavesurfer.getDuration(),
        color: "rgba(190, 250, 210, 0.5)",
      });
    } catch (err) {
      console.error("Error creating initial region:", err);
    }
  });

  // Keyboard controls if we want them
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

  // Questions for the read text
  async function handleQuestionSubmit(event) {
    event.preventDefault();
    console.log("Submitting answers...");

    const form = event.target;
    const recordingId =
      document.getElementById("questions-section").dataset.recordingId;
    console.log("Recording ID:", recordingId);

    // Get all answers from the form
    const answers = {};
    const formData = new FormData(form);
    formData.forEach((value, key) => {
      const matches = key.match(/answers\[(\d+)\]/);
      if (matches) {
        answers[matches[1]] = value;
      }
    });

    console.log("Answers to submit:", answers);

    // Create submission FormData
    const submitFormData = new FormData();
    submitFormData.append("action", "ra_submit_answers");
    submitFormData.append("nonce", raAjax.nonce);
    submitFormData.append("recording_id", recordingId);
    submitFormData.append("answers", JSON.stringify(answers));

    try {
      const response = await fetch(raAjax.ajax_url, {
        method: "POST",
        body: submitFormData,
        credentials: "same-origin",
      });

      // Log raw response for debugging
      const rawResponse = await response.clone().text();
      console.log("Raw response:", rawResponse);

      let responseData;
      try {
        responseData = JSON.parse(rawResponse);
      } catch (e) {
        console.error("Failed to parse response:", e);
        throw new Error("Ogiltig respons från servern");
      }

      if (responseData && responseData.success) {
        status.textContent = "Dina svar har sparats!";
        questionsSection.style.display = "none";
        uploadBtn.disabled = false;
        uploadBtn.textContent = "Ladda upp";
      } else {
        const errorMessage =
          responseData?.data?.message || "Kunde inte spara svaren";
        throw new Error(errorMessage);
      }
    } catch (error) {
      console.error("Error submitting answers:", error);
      status.textContent = `Fel vid sparande av svar: ${error.message}`;

      // Re-enable form submission
      const submitButton = form.querySelector('button[type="submit"]');
      if (submitButton) {
        submitButton.disabled = false;
      }
    }
  }
}
