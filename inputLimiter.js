/* =========================================================
   INTENT-AWARE SINGLE PHRASE LIMITER
   - Detects second conversational intent
   - Not word-count based
   - Not punctuation based
   - Locks immediately when new phrase begins
   - Includes 0/500 counter
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    const textarea = document.getElementById("inputText");
    if (!textarea) return;

    const MAX_CHARS = 500;

    /* ---------- Wrap textarea ---------- */

    const wrapper = document.createElement("div");
    wrapper.style.position = "relative";
    textarea.parentNode.insertBefore(wrapper, textarea);
    wrapper.appendChild(textarea);

    /* ---------- Counter ---------- */

    const counter = document.createElement("div");
    counter.style.position = "absolute";
    counter.style.bottom = "8px";
    counter.style.left = "12px";
    counter.style.fontSize = "12px";
    counter.style.color = "#6b7280";
    counter.textContent = `0/${MAX_CHARS}`;
    wrapper.appendChild(counter);

    /* ---------- Intent Detection Rules ---------- */

    const intentStarters = [
        // Question starters
        "ano","anong","saan","nasaan","kailan",
        "bakit","paano","sino","ilan",
        "kumusta","kamusta",

        // Strong clause resets
        "ako","ikaw","siya","kami","tayo",
        "pupunta","gusto","kailangan","may"
    ];

    function detectSecondIntent(text) {

        const words = text.toLowerCase().trim().split(/\s+/);

        let firstIntentDetected = false;

        for (let i = 0; i < words.length; i++) {

            if (intentStarters.includes(words[i])) {

                if (!firstIntentDetected) {
                    firstIntentDetected = true;
                } else {
                    return i; // return position of second intent
                }
            }
        }

        return -1;
    }

    function enforceLimit() {

        let value = textarea.value.replace(/[\r\n]+/g, " ");

        // Character cap
        if (value.length > MAX_CHARS) {
            value = value.substring(0, MAX_CHARS);
        }

        const secondIntentIndex = detectSecondIntent(value);

        if (secondIntentIndex !== -1) {

            const words = value.split(/\s+/);
            const trimmed = words.slice(0, secondIntentIndex).join(" ");

            textarea.value = trimmed;
            textarea.setAttribute("readonly", true);
        } else {
            textarea.value = value;
        }

        counter.textContent = `${textarea.value.length}/${MAX_CHARS}`;
        counter.style.color = textarea.value.length >= MAX_CHARS ? "red" : "#6b7280";
    }

    textarea.addEventListener("input", enforceLimit);

    textarea.addEventListener("keydown", function (e) {

        if (textarea.hasAttribute("readonly")) {

            if (e.key === "Backspace" || e.key === "Delete") {
                textarea.removeAttribute("readonly");
                setTimeout(enforceLimit, 0);
            } else {
                e.preventDefault();
            }
        }
    });

});