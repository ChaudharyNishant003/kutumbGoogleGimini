document.addEventListener('DOMContentLoaded', function () {
  var toggle = document.getElementById('chat-toggle');
  var panel = document.getElementById('chat-panel');
  var messages = document.getElementById('chat-messages');
  var form = document.getElementById('chat-form');
  var input = document.getElementById('chat-input');
  if (!toggle || !panel || !form) return;

  var opened = false;
  function setOpen(next) {
    opened = next;
    panel.classList.toggle('open', opened);
    if (opened) {
      input.focus();
      // A visitor who already reached the answer limit sees the phone form straight away.
      if (window.KutumbLeadCapture) window.KutumbLeadCapture.restore(messages, 'chat-bubble bot');
    }
  }
  toggle.addEventListener('click', function () { setOpen(!opened); });

  // Lets other widgets (e.g. the hero "Ask us anything" teaser bar) open this
  // same chat — one real assistant/back end, not a second fake reply pipeline.
  document.addEventListener('kutumb:chat-open', function (e) {
    setOpen(true);
    var prefill = e.detail && e.detail.prefill;
    if (!prefill) return;
    if (e.detail.autoSend) {
      submitMessage(prefill);
    } else {
      input.value = prefill;
      input.focus();
    }
  });

  function addBubble(text, who) {
    var bubble = document.createElement('div');
    bubble.className = 'chat-bubble ' + who;
    bubble.textContent = text;
    messages.appendChild(bubble);
    messages.scrollTop = messages.scrollHeight;
    return bubble;
  }

  function submitMessage(text) {
    text = (text || '').trim();
    if (!text) return;
    addBubble(text, 'user');
    input.value = '';
    var thinking = addBubble('Typing…', 'bot');

    fetch('/api/chatbot.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text })
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        thinking.textContent = data.reply || 'Sorry, something went wrong. Please try again.';
        // Requirement buttons or the name + phone form, when the server asks for them.
        if (data.next && window.KutumbLeadCapture) window.KutumbLeadCapture.show(messages, data.next, 'chat-bubble bot');
      })
      .catch(function () {
        thinking.textContent = 'Sorry, I could not reach the assistant right now.';
      });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    submitMessage(input.value);
  });
});
