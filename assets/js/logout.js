

document.addEventListener("DOMContentLoaded", () => {
  const btn = document.getElementById("logoutBtn");
  if (!btn) return; 

  btn.addEventListener("click", async (e) => {
    e.preventDefault();

    const baseUrl = document.body.dataset.baseUrl || "";

    try {
      await axios.post(
        `${baseUrl}/api/logout.php`,
        {},
        {
          headers: { "Content-Type": "application/json" },
          withCredentials: true,
        },
      );
    } catch (err) {
      console.warn("[logout] request failed, redirecting anyway", err);
    }

    
    window.location.href = `${baseUrl}/index.php`;
  });
});
