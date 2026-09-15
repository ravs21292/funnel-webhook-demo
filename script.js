(() => {
  const form = document.getElementById("lead-form");
  const submitBtn = document.getElementById("submit-btn");
  const responseBox = document.getElementById("response-box");

  const PRODUCT_PRICES = {
    starter: 29,
    growth: 79,
    pro: 149,
  };

  function showError(field, message) {
    const el = document.querySelector(`[data-error-for="${field}"]`);
    if (!el) return;
    el.textContent = message;
    el.hidden = !message;
  }

  function clearErrors() {
    document.querySelectorAll("[data-error-for]").forEach((el) => {
      el.textContent = "";
      el.hidden = true;
    });
  }

  function validate(data) {
    const errors = {};

    if (!data.name || data.name.trim().length < 2) {
      errors.name = "Enter your full name (at least 2 characters).";
    }

    const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email || "");
    if (!emailOk) {
      errors.email = "Enter a valid email address.";
    }

    if (data.phone) {
      const digits = data.phone.replace(/\D/g, "");
      if (digits.length < 7 || digits.length > 15) {
        errors.phone = "Phone should have 7–15 digits.";
      }
    }

    if (!data.product || !PRODUCT_PRICES[data.product]) {
      errors.product = "Select a product.";
    }

    return errors;
  }

  function buildPayload(formData) {
    const product = formData.get("product");
    return {
      event: "lead_submitted",
      source: "funnel-webhook-demo",
      timestamp: new Date().toISOString(),
      lead: {
        name: String(formData.get("name") || "").trim(),
        email: String(formData.get("email") || "").trim().toLowerCase(),
        phone: String(formData.get("phone") || "").trim() || null,
        notes: String(formData.get("notes") || "").trim() || null,
      },
      order: {
        product,
        amount: PRODUCT_PRICES[product] ?? null,
        currency: "USD",
      },
    };
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    clearErrors();

    const formData = new FormData(form);
    const payload = buildPayload(formData);
    const errors = validate({
      name: payload.lead.name,
      email: payload.lead.email,
      phone: payload.lead.phone,
      product: payload.order.product,
    });

    if (Object.keys(errors).length) {
      Object.entries(errors).forEach(([field, message]) => showError(field, message));
      responseBox.textContent = "Fix the highlighted fields, then try again.";
      return;
    }

    submitBtn.disabled = true;
    responseBox.textContent = "Sending…";

    try {
      const res = await fetch("/webhook.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify(payload),
      });

      const text = await res.text();
      let body;
      try {
        body = JSON.parse(text);
      } catch {
        body = { raw: text };
      }

      responseBox.textContent = JSON.stringify(
        { httpStatus: res.status, body },
        null,
        2
      );
    } catch (err) {
      responseBox.textContent = JSON.stringify(
        {
          error: "Network or CORS failure",
          message: err instanceof Error ? err.message : String(err),
        },
        null,
        2
      );
    } finally {
      submitBtn.disabled = false;
    }
  });
})();
