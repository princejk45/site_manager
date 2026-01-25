// Check for new messages every 30 seconds
function checkUnreadMessages() {
  const userId = document.querySelector('meta[name="user-id"]').content;
  fetch(`/api/messages/unread?user_id=${userId}`)
    .then((response) => response.json())
    .then((data) => {
      const badge = document.querySelector(".notification-badge");
      if (data.unread > 0) {
        badge.textContent = data.unread;
        badge.style.display = "inline-block";

        // Optional desktop notification
        if (data.latest && Notification.permission === "granted") {
          new Notification(`New message from ${data.latest.sender}`, {
            body: data.latest.preview,
          });
        }
      } else {
        badge.style.display = "none";
      }
    })
    .catch((error) => console.error("Error checking messages:", error));
}

// Mark as read when opening a thread
function markThreadAsRead(threadId) {
  const userId = document.querySelector('meta[name="user-id"]').content;
  fetch("/api/messages/mark-read", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      thread_id: threadId,
      user_id: userId,
    }),
  }).catch((error) => console.error("Error marking as read:", error));
}

// Initialize
document.addEventListener("DOMContentLoaded", () => {
  // Check every 30 seconds
  setInterval(checkUnreadMessages, 30000);

  // Request notification permission
  if ("Notification" in window && Notification.permission !== "granted") {
    Notification.requestPermission();
  }

  // Initial check
  checkUnreadMessages();

  // Auto-mark as read when viewing a thread
  const urlParams = new URLSearchParams(window.location.search);
  if (
    urlParams.get("action") === "messaging" &&
    urlParams.get("do") === "view"
  ) {
    const threadId = urlParams.get("id");
    if (threadId) markThreadAsRead(threadId);
  }
});
