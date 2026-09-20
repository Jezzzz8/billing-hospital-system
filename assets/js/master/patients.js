document.addEventListener("DOMContentLoaded", () => {
  const baseUrl = document.body.dataset.baseUrl || "";
  const alertBox = document.getElementById("alert");

  const modal = document.getElementById("patientModal");
  const form = document.getElementById("patientForm");
  const modalTitle = document.getElementById("patientModalTitle");
  const submitBtn = document.getElementById("patientSubmitBtn");
  const submitLbl = document.getElementById("patientSubmitLabel");
  const openCreate = document.getElementById("openCreateBtn");

  const confirmModal = document.getElementById("confirmModal");
  const confirmPatientName = document.getElementById("confirmPatientName");
  const confirmDeactivateBtn = document.getElementById("confirmDeactivateBtn");
  const confirmDeactivateLbl = document.getElementById("confirmDeactivateLabel");

  const tabButtons = document.querySelectorAll(".tab-btn");
  const tabPanels = document.querySelectorAll(".tab-panel");
  const prevBtn = document.getElementById("prevTab");
  const nextBtn = document.getElementById("nextTab");

  const createAdmissionCb = document.getElementById("create_admission");
  const admissionFields = document.getElementById("admissionFields");
  const roomFields = document.getElementById("roomFields");
  const doctorsFields = document.getElementById("doctorsFields");
  const diagnosesFields = document.getElementById("diagnosesFields");

  const holder = document.getElementById("patientsData");
  let DOCTORS = [];
  let DIAGNOSES = [];

  if (holder) {
    try { DOCTORS = JSON.parse(holder.dataset.doctors || "[]"); } catch (e) { console.error(e); }
    try { DIAGNOSES = JSON.parse(holder.dataset.diagnoses || "[]"); } catch (e) { console.error(e); }
  }

  const hardReload = () => {
    const url = new URL(window.location.href);
    url.searchParams.set("_r", Date.now().toString());
    window.location.replace(url.toString());
  };

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

  function removeInjectedRoomOptions() {
    document
      .querySelectorAll('#room_id option[data-injected="1"]')
      .forEach((o) => o.remove());
  }

  function applyRoomOccupancy(editingAdmissionId) {
    const roomSel = document.getElementById("room_id");
    if (!roomSel) return;

    const editingId = String(editingAdmissionId || 0);

    Array.from(roomSel.options).forEach((opt) => {
      if (!opt.value) {
        opt.disabled = false;
        return;
      }
      if (opt.dataset.injected === "1") {
        opt.disabled = false;
        return;
      }

      const occupiedBy = String(opt.dataset.occupiedBy || 0);
      const isBlocked =
        occupiedBy !== "0" && occupiedBy !== "" && occupiedBy !== editingId;

      opt.disabled = isBlocked;
    });
  }

  function selectCurrentRoom(p) {
    const roomSel = document.getElementById("room_id");
    if (!roomSel) return;

    const ra = p.room_assignment;

    applyRoomOccupancy(p.admission_id);

    if (!ra || !ra.room_id) {
      roomSel.value = "";
      return;
    }

    const roomIdStr = String(ra.room_id);
    let opt = Array.from(roomSel.options).find(
      (o) => String(o.value) === roomIdStr,
    );

    if (!opt) {
      opt = document.createElement("option");
      opt.value = roomIdStr;
      opt.dataset.injected = "1";
      opt.textContent =
        (ra.room_number || "Room") +
        " — " +
        (ra.room_type_name || "") +
        (ra.is_active == 1 ? "" : " (past)");
      roomSel.appendChild(opt);
    }

    opt.disabled = false;
    roomSel.value = roomIdStr;
  }

  let currentTab = "info";
  const TAB_ORDER = ["info", "admission", "room", "doctors", "diagnoses"];

  function setActiveTab(tab) {
    currentTab = tab;
    tabButtons.forEach((b) => {
      const active = b.dataset.tab === tab;
      b.classList.toggle("border-blue-600", active);
      b.classList.toggle("text-blue-600", active);
      b.classList.toggle("border-transparent", !active);
      b.classList.toggle("text-slate-500", !active);
    });
    tabPanels.forEach((p) => {
      p.classList.toggle("hidden", p.dataset.panel !== tab);
    });
    const idx = TAB_ORDER.indexOf(tab);
    if (prevBtn) prevBtn.classList.toggle("hidden", idx <= 0);
    if (nextBtn) nextBtn.classList.toggle("hidden", idx >= TAB_ORDER.length - 1);
    if (submitBtn) submitBtn.classList.toggle("hidden", idx < TAB_ORDER.length - 1);
  }

  tabButtons.forEach((b) =>
    b.addEventListener("click", () => setActiveTab(b.dataset.tab)),
  );
  prevBtn?.addEventListener("click", () => {
    const idx = TAB_ORDER.indexOf(currentTab);
    if (idx > 0) setActiveTab(TAB_ORDER[idx - 1]);
  });
  nextBtn?.addEventListener("click", () => {
    const idx = TAB_ORDER.indexOf(currentTab);
    if (idx < TAB_ORDER.length - 1) setActiveTab(TAB_ORDER[idx + 1]);
  });

  function toggleAdmissionFields() {
    const on = createAdmissionCb?.checked;
    [admissionFields, roomFields, doctorsFields, diagnosesFields].forEach((el) => {
      if (!el) return;
      el.classList.toggle("opacity-50", !on);
      el.classList.toggle("pointer-events-none", !on);
    });
  }
  createAdmissionCb?.addEventListener("change", toggleAdmissionFields);

  function addDoctorRow(selectedId = "", selectedRole = "Attending") {
    const list = document.getElementById("doctorsList");
    if (!list) return;
    const row = document.createElement("div");
    row.className = "flex items-center gap-2 doctor-row";
    const options = DOCTORS.map(
      (d) => `<option value="${d.doctor_id}" ${d.doctor_id == selectedId ? "selected" : ""}>${d.name} (₱${Number(d.fee).toFixed(2)})</option>`,
    ).join("");
    row.innerHTML = `
      <select class="doctor-select flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
        <option value="">— Select doctor —</option>
        ${options}
      </select>
      <select class="doctor-role rounded-lg border border-slate-300 px-2 py-2 text-sm bg-white">
        <option value="Attending" ${selectedRole === "Attending" ? "selected" : ""}>Attending</option>
        <option value="Consulting" ${selectedRole === "Consulting" ? "selected" : ""}>Consulting</option>
        <option value="Referring" ${selectedRole === "Referring" ? "selected" : ""}>Referring</option>
      </select>
      <button type="button" class="remove-row-btn text-rose-500 hover:text-rose-700 p-1">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    `;
    row.querySelector(".remove-row-btn").addEventListener("click", () => row.remove());
    list.appendChild(row);
  }

  function addDiagnosisRow(selectedId = "", selectedType = "Primary") {
    const list = document.getElementById("diagnosesList");
    if (!list) return;
    const row = document.createElement("div");
    row.className = "flex items-center gap-2 diagnosis-row";
    const options = DIAGNOSES.map(
      (d) => `<option value="${d.diagnosis_id}" ${d.diagnosis_id == selectedId ? "selected" : ""}>${d.icd_code ? "[" + d.icd_code + "] " : ""}${d.diagnosis_name}</option>`,
    ).join("");
    row.innerHTML = `
      <select class="diagnosis-select flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
        <option value="">— Select diagnosis —</option>
        ${options}
      </select>
      <select class="diagnosis-type rounded-lg border border-slate-300 px-2 py-2 text-sm bg-white">
        <option value="Primary" ${selectedType === "Primary" ? "selected" : ""}>Primary</option>
        <option value="Secondary" ${selectedType === "Secondary" ? "selected" : ""}>Secondary</option>
        <option value="Differential" ${selectedType === "Differential" ? "selected" : ""}>Differential</option>
        <option value="Admitting" ${selectedType === "Admitting" ? "selected" : ""}>Admitting</option>
      </select>
      <button type="button" class="remove-row-btn text-rose-500 hover:text-rose-700 p-1">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    `;
    row.querySelector(".remove-row-btn").addEventListener("click", () => row.remove());
    list.appendChild(row);
  }

  document.getElementById("addDoctorRow")?.addEventListener("click", () => addDoctorRow());
  document.getElementById("addDiagnosisRow")?.addEventListener("click", () => addDiagnosisRow());

  document.querySelectorAll("[data-close-modal]").forEach((el) => {
    el.addEventListener("click", () => closeModal(modal));
  });
  document.querySelectorAll("[data-close-confirm]").forEach((el) => {
    el.addEventListener("click", () => closeModal(confirmModal));
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeModal(modal);
      closeModal(confirmModal);
    }
  });

  if (openCreate) {
    openCreate.addEventListener("click", () => {
      form.reset();
      clearErrors(form);
      removeInjectedRoomOptions();
      document.getElementById("patient_id").value = "";
      document.getElementById("admission_id").value = "";
      document.getElementById("doctorsList").innerHTML = "";
      document.getElementById("diagnosesList").innerHTML = "";
      if (createAdmissionCb) createAdmissionCb.disabled = false;
      modalTitle.textContent = "New Patient";
      submitLbl.textContent = "Save Patient";
      applyRoomOccupancy(0);
      toggleAdmissionFields();
      setActiveTab("info");
      openModal(modal);
    });
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    hideAlert();
    clearErrors(form);

    const id = document.getElementById("patient_id").value;
    const isEdit = id !== "";

    const doctors = [];
    document.querySelectorAll(".doctor-row").forEach((row) => {
      const dId = row.querySelector(".doctor-select")?.value;
      const dRole = row.querySelector(".doctor-role")?.value;
      if (dId) doctors.push({ doctor_id: parseInt(dId, 10), doctor_role: dRole });
    });

    const diagnoses = [];
    document.querySelectorAll(".diagnosis-row").forEach((row) => {
      const diId = row.querySelector(".diagnosis-select")?.value;
      const diType = row.querySelector(".diagnosis-type")?.value;
      if (diId) diagnoses.push({ diagnosis_id: parseInt(diId, 10), diagnosis_type: diType });
    });

    const payload = {
      gender_id: document.getElementById("gender_id").value,
      first_name: document.getElementById("first_name").value.trim(),
      last_name: document.getElementById("last_name").value.trim(),
      birth_date: document.getElementById("birth_date").value,
      email: document.getElementById("email").value.trim(),
      contact_number: document.getElementById("contact_number").value.trim(),
      address: document.getElementById("address").value.trim(),
      emergency_contact: document.getElementById("emergency_contact").value.trim(),
      emergency_contact_number: document.getElementById("emergency_contact_number").value.trim(),
      medical_history: document.getElementById("medical_history").value.trim(),
      create_admission: createAdmissionCb?.checked ? 1 : 0,
      admission_status_id: document.getElementById("admission_status_id")?.value || "",
      admission_type: document.getElementById("admission_type")?.value || "",
      chief_complaint: document.getElementById("chief_complaint")?.value.trim() || "",
      admission_notes: document.getElementById("admission_notes")?.value.trim() || "",
      room_id: document.getElementById("room_id")?.value || 0,
      doctors: doctors,
      diagnoses: diagnoses,
    };

    if (isEdit) {
      payload.admission_id = document.getElementById("admission_id").value || 0;
    }

    const url = isEdit
      ? `${baseUrl}/api/patients/update.php?id=${id}`
      : `${baseUrl}/api/patients/create.php`;

    submitBtn.disabled = true;
    submitLbl.textContent = isEdit ? "Saving…" : "Creating…";

    try {
      const { data } = await axios.post(url, payload, {
        headers: { "Content-Type": "application/json" },
        withCredentials: true,
      });

      if (data.success) {
        closeModal(modal);
        sessionStorage.setItem("patient_flash", data.message || "Saved.");
        hardReload();
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
      submitLbl.textContent = isEdit ? "Save Changes" : "Save Patient";
    }
  });

  document.querySelectorAll(".edit-btn").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const id = btn.dataset.patientId;
      form.reset();
      clearErrors(form);
      removeInjectedRoomOptions();
      document.getElementById("doctorsList").innerHTML = "";
      document.getElementById("diagnosesList").innerHTML = "";
      modalTitle.textContent = "Edit Patient";
      submitLbl.textContent = "Save Changes";
      document.getElementById("patient_id").value = id;
      openModal(modal);

      try {
        const { data } = await axios.get(
          `${baseUrl}/api/patients/get-details.php?id=${id}`,
          { withCredentials: true },
        );
        if (!data.success) return;

        const p = data.data;
        document.getElementById("first_name").value = p.first_name || "";
        document.getElementById("last_name").value = p.last_name || "";
        document.getElementById("gender_id").value = p.gender_id || "";
        document.getElementById("birth_date").value = p.birth_date || "";
        document.getElementById("email").value = p.email || "";
        document.getElementById("contact_number").value = p.contact_number || "";
        document.getElementById("address").value = p.address || "";
        document.getElementById("emergency_contact").value = p.emergency_contact || "";
        document.getElementById("emergency_contact_number").value = p.emergency_contact_number || "";
        document.getElementById("medical_history").value = p.medical_history || "";

        document.getElementById("admission_id").value = p.admission_id || "";
        document.getElementById("admission_status_id").value = p.admission_status_id || 1;
        document.getElementById("admission_type").value = p.admission_type || "Emergency";
        document.getElementById("chief_complaint").value = p.chief_complaint || "";
        document.getElementById("admission_notes").value = p.admission_notes || "";

        selectCurrentRoom(p);

        if (p.admission_id) {
          createAdmissionCb.checked = true;
          createAdmissionCb.disabled = false;
        } else {
          createAdmissionCb.checked = false;
          createAdmissionCb.disabled = false;
        }

        (p.doctors || []).forEach((d) => addDoctorRow(d.doctor_id, d.doctor_role));
        (p.diagnoses || []).forEach((d) => addDiagnosisRow(d.diagnosis_id, d.diagnosis_type));

        toggleAdmissionFields();
        setActiveTab("info");
      } catch (err) {
        console.error(err);
        showAlert("Could not load patient details.", "error");
      }
    });
  });

  let pendingArchiveId = null;

  document.querySelectorAll(".deactivate-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      pendingArchiveId = btn.getAttribute("data-patient-id");
      confirmPatientName.textContent =
        btn.getAttribute("data-patient-name") || "this patient";
      openModal(confirmModal);
    });
  });

  if (confirmDeactivateBtn) {
    confirmDeactivateBtn.addEventListener("click", async () => {
      if (!pendingArchiveId) return;

      confirmDeactivateBtn.disabled = true;
      confirmDeactivateLbl.textContent = "Archiving…";

      try {
        const { data } = await axios.post(
          `${baseUrl}/api/patients/toggle-active.php?id=${pendingArchiveId}`,
          {},
          {
            headers: { "Content-Type": "application/json" },
            withCredentials: true,
          },
        );

        if (data.success) {
          closeModal(confirmModal);
          sessionStorage.setItem("patient_flash", data.message || "Patient archived.");
          hardReload();
        } else {
          closeModal(confirmModal);
          showAlert(data.message || "Action failed.", "error");
        }
      } catch (err) {
        closeModal(confirmModal);
        showAlert(err.response?.data?.message || "Action failed.", "error");
      } finally {
        confirmDeactivateBtn.disabled = false;
        confirmDeactivateLbl.textContent = "Archive Patient";
        pendingArchiveId = null;
      }
    });
  }

  document.querySelectorAll(".reactivate-btn").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const id = btn.getAttribute("data-patient-id");
      if (!id) return;

      btn.disabled = true;

      try {
        const { data } = await axios.post(
          `${baseUrl}/api/patients/toggle-active.php?id=${id}`,
          {},
          {
            headers: { "Content-Type": "application/json" },
            withCredentials: true,
          },
        );

        if (data.success) {
          sessionStorage.setItem("patient_flash", data.message || "Patient reactivated.");
          hardReload();
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

  const rows = Array.from(document.querySelectorAll(".patient-row"));
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
  setActiveTab("info");

  const flash = sessionStorage.getItem("patient_flash");
  if (flash) {
    sessionStorage.removeItem("patient_flash");
    showAlert(flash, "success");
  }
});