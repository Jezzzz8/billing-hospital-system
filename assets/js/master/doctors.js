

document.addEventListener("DOMContentLoaded", () => {
  const baseUrl = document.body.dataset.baseUrl || "";
  const alertBox = document.getElementById("alert");

  
  const modal = document.getElementById("doctorModal");
  const form = document.getElementById("doctorForm");
  const modalTitle = document.getElementById("doctorModalTitle");
  const submitBtn = document.getElementById("doctorSubmitBtn");
  const submitLbl = document.getElementById("doctorSubmitLabel");
  const openCreate = document.getElementById("openCreateBtn");

  
  const pwModal = document.getElementById("pwModal");
  const pwForm = document.getElementById("pwForm");
  const pwDoctorName = document.getElementById("pwDoctorName");
  const pwSubmitBtn = document.getElementById("pwSubmitBtn");
  const pwSubmitLbl = document.getElementById("pwSubmitLabel");

  
  const confirmModal = document.getElementById("confirmModal");
  const confirmName = document.getElementById("confirmDoctorName");
  const confirmDeactivateBtn = document.getElementById("confirmDeactivateBtn");
  const confirmDeactivateLbl = document.getElementById(
    "confirmDeactivateLabel",
  );

  
  const specCheckboxes = document.querySelectorAll(".spec-checkbox");
  const specCount = document.getElementById("specCount");
  const primaryWrapper = document.getElementById("primaryWrapper");
  const primarySelect = document.getElementById("primary_specialization_id");
  const passwordFieldWrap = document.getElementById("passwordFieldWrapper");

  
  const showAlert = (msg, type = "error") => {
    const styles = {
      error: "bg-red-50 border-red-200 text-red-700",
      success: "bg-emerald-50 border-emerald-200 text-emerald-700",
    };
    alertBox.className = `mb-5 rounded-lg px-4 py-3 text-sm border ${styles[type] || styles.error}`;
    alertBox.textContent = msg;
    alertBox.classList.remove("hidden");
    alertBox.scrollIntoView({ behavior: "smooth", block: "nearest" });
  };
  const hideAlert = () => alertBox.classList.add("hidden");

  const clearErrors = (scope) => {
    scope.querySelectorAll("[data-error-for]").forEach((p) => {
      p.textContent = "";
      p.classList.add("hidden");
    });
  };
  const setError = (scope, field, msg) => {
    const p = scope.querySelector(`[data-error-for="${field}"]`);
    if (!p) return;
    p.textContent = msg || "";
    p.classList.toggle("hidden", !msg);
  };

  const openModal = (el) => el.classList.remove("hidden");
  const closeModal = (el) => el.classList.add("hidden");

  
  function refreshSpecializationUI() {
    const checked = Array.from(specCheckboxes).filter((cb) => cb.checked);

    
    if (specCount) specCount.textContent = `${checked.length} selected`;

    
    specCheckboxes.forEach((cb) => {
      const wrapper = cb.closest(".spec-checkbox-wrapper");
      if (!wrapper) return;
      if (cb.checked) {
        wrapper.classList.add("bg-blue-50", "border-blue-300");
        wrapper.classList.remove("border-slate-200");
      } else {
        wrapper.classList.remove("bg-blue-50", "border-blue-300");
        wrapper.classList.add("border-slate-200");
      }
    });

    
    const previousValue = primarySelect?.value || "";
    if (primarySelect) {
      primarySelect.innerHTML =
        '<option value="">— None marked as primary —</option>';
      checked.forEach((cb) => {
        const label =
          cb.closest("label")?.querySelector("span")?.textContent?.trim() || "";
        const opt = document.createElement("option");
        opt.value = cb.value;
        opt.textContent = label;
        if (cb.value === previousValue) opt.selected = true;
        primarySelect.appendChild(opt);
      });
    }

    
    if (primaryWrapper)
      primaryWrapper.classList.toggle("hidden", checked.length < 2);
  }

  specCheckboxes.forEach((cb) =>
    cb.addEventListener("change", refreshSpecializationUI),
  );

  
  document.querySelectorAll("[data-close-modal]").forEach((el) => {
    el.addEventListener("click", () => closeModal(modal));
  });
  document.querySelectorAll("[data-close-pw]").forEach((el) => {
    el.addEventListener("click", () => closeModal(pwModal));
  });
  document.querySelectorAll("[data-close-confirm]").forEach((el) => {
    el.addEventListener("click", () => closeModal(confirmModal));
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeModal(modal);
      closeModal(pwModal);
      closeModal(confirmModal);
    }
  });

  
  
  
  if (openCreate) {
    openCreate.addEventListener("click", () => {
      form.reset();
      clearErrors(form);
      document.getElementById("doctor_id").value = "";
      specCheckboxes.forEach((cb) => (cb.checked = false));
      modalTitle.textContent = "New Doctor";
      submitLbl.textContent = "Create Doctor";
      passwordFieldWrap.classList.remove("hidden");
      document.getElementById("password").required = true;
      refreshSpecializationUI();
      openModal(modal);
    });
  }

  
  
  
  document.querySelectorAll(".edit-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      const d = JSON.parse(btn.dataset.doctor);

      form.reset();
      clearErrors(form);

      modalTitle.textContent = "Edit Doctor";
      submitLbl.textContent = "Save Changes";

      document.getElementById("doctor_id").value = d.doctor_id;
      document.getElementById("first_name").value = d.first_name;
      document.getElementById("last_name").value = d.last_name;
      document.getElementById("username").value = d.username;
      document.getElementById("email").value = d.email;
      document.getElementById("contact_number").value = d.contact_number || "";
      document.getElementById("license_number").value = d.license_number;
      document.getElementById("consultation_fee").value = d.consultation_fee;

      
      specCheckboxes.forEach((cb) => (cb.checked = false));
      let primaryId = null;
      (d.specializations || []).forEach((s) => {
        const cb = Array.from(specCheckboxes).find(
          (x) => x.value === String(s.specialization_id),
        );
        if (cb) {
          cb.checked = true;
          if (String(s.is_primary).toLowerCase() === "yes")
            primaryId = s.specialization_id;
        }
      });

      refreshSpecializationUI();
      if (primarySelect && primaryId) primarySelect.value = String(primaryId);

      passwordFieldWrap.classList.add("hidden");
      document.getElementById("password").required = false;
      document.getElementById("password").value = "";

      openModal(modal);
    });
  });

  
  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    hideAlert();
    clearErrors(form);

    const id = document.getElementById("doctor_id").value;
    const isEdit = id !== "";

    const specializations = Array.from(specCheckboxes)
      .filter((cb) => cb.checked)
      .map((cb) => parseInt(cb.value, 10));

    const payload = {
      first_name: document.getElementById("first_name").value.trim(),
      last_name: document.getElementById("last_name").value.trim(),
      username: document.getElementById("username").value.trim(),
      email: document.getElementById("email").value.trim(),
      contact_number: document.getElementById("contact_number").value.trim(),
      license_number: document.getElementById("license_number").value.trim(),
      consultation_fee: document.getElementById("consultation_fee").value,
      specializations: specializations,
      primary_specialization_id: primarySelect?.value
        ? parseInt(primarySelect.value, 10)
        : 0,
    };
    if (!isEdit) payload.password = document.getElementById("password").value;

    const url = isEdit
      ? `${baseUrl}/api/doctors/update.php?id=${id}`
      : `${baseUrl}/api/doctors/create.php`;

    submitBtn.disabled = true;
    submitLbl.textContent = isEdit ? "Saving…" : "Creating…";

    try {
      const { data } = await axios.post(url, payload, {
        headers: { "Content-Type": "application/json" },
        withCredentials: true,
      });

      if (data.success) {
        closeModal(modal);
        sessionStorage.setItem("doctor_flash", data.message || "Saved.");
        window.location.reload();
      } else {
        if (data.errors) {
          Object.entries(data.errors).forEach(([f, m]) => setError(form, f, m));
        }
        showAlert(data.message || "Save failed.", "error");
      }
    } catch (err) {
      const res = err.response?.data;
      if (res?.errors) {
        Object.entries(res.errors).forEach(([f, m]) => setError(form, f, m));
      }
      showAlert(res?.message || "Save failed.", "error");
    } finally {
      submitBtn.disabled = false;
      submitLbl.textContent = isEdit ? "Save Changes" : "Create Doctor";
    }
  });

  
  
  
  document.querySelectorAll(".pw-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      pwForm.reset();
      clearErrors(pwForm);
      pwDoctorName.textContent = btn.dataset.doctorName || "";
      pwForm.dataset.doctorId = btn.dataset.doctorId;
      openModal(pwModal);
    });
  });

  pwForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    hideAlert();
    clearErrors(pwForm);

    const doctorId = pwForm.dataset.doctorId;
    const password = document.getElementById("pw_password").value;
    const confirm = document.getElementById("pw_password_confirm").value;

    pwSubmitBtn.disabled = true;
    pwSubmitLbl.textContent = "Updating…";

    try {
      const { data } = await axios.post(
        `${baseUrl}/api/doctors/change-password.php?id=${doctorId}`,
        { password, password_confirm: confirm },
        {
          headers: { "Content-Type": "application/json" },
          withCredentials: true,
        },
      );

      if (data.success) {
        closeModal(pwModal);
        sessionStorage.setItem(
          "doctor_flash",
          data.message || "Password updated.",
        );
        window.location.reload();
      } else {
        if (data.errors) {
          Object.entries(data.errors).forEach(([f, m]) =>
            setError(pwForm, f, m),
          );
        }
        showAlert(data.message || "Update failed.", "error");
      }
    } catch (err) {
      const res = err.response?.data;
      if (res?.errors) {
        Object.entries(res.errors).forEach(([f, m]) => setError(pwForm, f, m));
      }
      showAlert(res?.message || "Update failed.", "error");
    } finally {
      pwSubmitBtn.disabled = false;
      pwSubmitLbl.textContent = "Update Password";
    }
  });

  
  
  
  document.querySelectorAll(".deactivate-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      confirmName.textContent = btn.getAttribute("data-doctor-name");
      confirmDeactivateBtn.dataset.doctorId =
        btn.getAttribute("data-doctor-id");
      openModal(confirmModal);
    });
  });

  if (confirmDeactivateBtn) {
    confirmDeactivateBtn.addEventListener("click", async () => {
      const id = confirmDeactivateBtn.dataset.doctorId;
      if (!id) return;

      confirmDeactivateBtn.disabled = true;
      confirmDeactivateLbl.textContent = "Archiving…";

      try {
        const { data } = await axios.post(
          `${baseUrl}/api/doctors/toggle-active.php?id=${id}`,
          {},
          {
            headers: { "Content-Type": "application/json" },
            withCredentials: true,
          },
        );

        if (data.success) {
          closeModal(confirmModal);
          sessionStorage.setItem(
            "doctor_flash",
            data.message || "Doctor archived.",
          );
          window.location.reload();
        } else {
          closeModal(confirmModal);
          showAlert(data.message || "Action failed.", "error");
        }
      } catch (err) {
        closeModal(confirmModal);
        showAlert(err.response?.data?.message || "Action failed.", "error");
      } finally {
        confirmDeactivateBtn.disabled = false;
        confirmDeactivateLbl.textContent = "Archive Doctor";
      }
    });
  }

  
  
  
  document.querySelectorAll(".reactivate-btn").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const id = btn.getAttribute("data-doctor-id");
      if (!id) return;

      btn.disabled = true;

      try {
        const { data } = await axios.post(
          `${baseUrl}/api/doctors/toggle-active.php?id=${id}`,
          {},
          {
            headers: { "Content-Type": "application/json" },
            withCredentials: true,
          },
        );

        if (data.success) {
          sessionStorage.setItem(
            "doctor_flash",
            data.message || "Doctor reactivated.",
          );
          window.location.reload();
        } else {
          showAlert(data.message || "Action failed.", "error");
        }
      } catch (err) {
        showAlert(err.response?.data?.message || "Action failed.", "error");
      } finally {
        btn.disabled = false;
      }
    });
  });

  
  
  
  const searchInput = document.getElementById("filterSearch");
  const showArchived = document.getElementById("showArchived");
  const filterClear = document.getElementById("filterClear");
  const filterSummary = document.getElementById("filterSummary");
  const filteredCount = document.getElementById("filteredCount");
  const totalCount = document.getElementById("totalCount");
  const emptyState = document.getElementById("emptyState");

  const rows = Array.from(document.querySelectorAll(".doctor-row"));
  if (totalCount) totalCount.textContent = rows.length;

  function applyFilters() {
    const q = (searchInput?.value || "").toLowerCase().trim();
    const showInactiveOnly = showArchived?.checked || false;

    let visible = 0;

    rows.forEach((row) => {
      const matchesSearch = q === "" || (row.dataset.search || "").includes(q);

      const isArchived = row.dataset.status === "0";
      const matchesArchiveFilter = showInactiveOnly ? isArchived : !isArchived;

      const show = matchesSearch && matchesArchiveFilter;
      row.classList.toggle("hidden", !show);
      if (show) visible++;
    });

    if (filteredCount) filteredCount.textContent = visible;

    const isFiltering = q !== "" || showInactiveOnly;
    if (filterSummary) filterSummary.classList.toggle("hidden", !isFiltering);
    if (emptyState) emptyState.classList.toggle("hidden", visible > 0);

    if (filterClear) {
      filterClear.classList.toggle("hidden", !isFiltering);
      filterClear.classList.toggle("inline-flex", isFiltering);
    }
  }

  if (searchInput) searchInput.addEventListener("input", applyFilters);
  if (showArchived) showArchived.addEventListener("change", applyFilters);
  if (filterClear) {
    filterClear.addEventListener("click", () => {
      searchInput.value = "";
      showArchived.checked = false;
      applyFilters();
    });
  }

  applyFilters();
  refreshSpecializationUI();

  
  
  
  const flash = sessionStorage.getItem("doctor_flash");
  if (flash) {
    sessionStorage.removeItem("doctor_flash");
    showAlert(flash, "success");
  }
});
