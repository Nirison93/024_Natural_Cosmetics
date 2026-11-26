<script setup>
import GuestLayout from "@/Layouts/GuestLayout.vue";
import InputError from "@/Components/InputError.vue";
import { Head, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import { useI18n } from "vue-i18n";

defineProps({
  canResetPassword: { type: Boolean },
  status: { type: String },
});

const { t, locale } = useI18n();

const form = useForm({
  email: "",
  password: "",
  remember: false,
});

const showPassword = ref(false);
const currentYear = new Date().getFullYear();

const toggleLanguage = () => {
  locale.value = locale.value === 'en' ? 'si' : 'en';
  localStorage.setItem('locale', locale.value);
};

const submit = () => {
  form.post(route("login"), {
    onFinish: () => form.reset("password"),
  });
};
</script>

<template>
  <GuestLayout>
    <Head :title="$t('auth.sign_in')" />

    <div class="login-root">
      <main class="login-shell">
        <aside class="brand-panel" aria-hidden="true">
          <div class="brand-panel-glow" />
          <svg class="botanical-art" viewBox="0 0 360 420" fill="none">
            <path class="botanical-stem" d="M171 396c18-85 21-168 11-261m3 141c34-61 66-106 112-144m-120 200c-39-55-77-88-124-112" />
            <path class="botanical-leaf" d="M185 215c-46-9-66-36-63-75 41 2 65 27 63 75Zm0 36c5-48 30-73 71-74 0 40-25 67-71 74Zm-7 45c-42-9-61-32-59-67 38 1 60 24 59 67Zm7 22c5-43 27-66 64-67 0 37-22 61-64 67ZM94 252c-34-21-43-48-28-79 32 14 43 41 28 79Zm169-106c-5-39 11-63 47-73 9 35-7 61-47 73Z" />
            <path class="botanical-vein" d="M127 144c18 20 34 43 49 69m18 34c20-27 43-51 67-71M119 229c20 17 38 37 56 60m11 27c17-17 36-32 58-45M72 173c7 26 15 50 23 72m191-164c-8 22-18 44-31 65" />
          </svg>
          <div class="brand-panel-footer">
            <span />
            <p>{{ $t('auth.point_of_sale') }}</p>
          </div>
        </aside>

      <!-- ── Centered Card ── -->
      <div class="center-wrap">
        <div class="form-card">

          <!-- Logo -->
          <div class="logo-row">
            <div class="logo-icon">
              <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M20 4c-8 0-14 3.5-14 9.5A5.5 5.5 0 0011.5 19C17.5 19 20 12.5 20 4ZM5 20c3-4.5 6.5-7.5 11-10" />
              </svg>
            </div>
            <div>
              <p class="logo-name">Online මුදලාලි</p>
              <p class="logo-sub">{{ $t('auth.point_of_sale') }}</p>
            </div>
            <!-- Language toggle on login page -->
            <button
              @click="toggleLanguage"
              type="button"
              class="language-toggle"
              :aria-label="locale === 'en' ? 'Switch language to Sinhala' : 'Switch language to English'"
            >
              <span :class="{ 'language-active': locale === 'en' }">EN</span>
              <span class="language-divider">/</span>
              <span :class="{ 'language-active': locale === 'si' }">සිං</span>
            </button>
          </div>

          <!-- Heading -->
          <h2 class="form-title">{{ $t('auth.welcome_back') }}</h2>
          <p class="form-sub">{{ $t('auth.sign_in_subtitle') }}</p>

          <!-- Status -->
          <div v-if="status" class="status-msg">
            <svg class="status-icon" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd"
                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                clip-rule="evenodd" />
            </svg>
            <span>{{ status }}</span>
          </div>

          <!-- Form -->
          <form @submit.prevent="submit" class="login-form">

            <!-- Email -->
            <div class="field">
              <label for="email" class="field-label">{{ $t('auth.email_address') }}</label>
              <div class="field-wrap">
                <svg class="field-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                <input
                  id="email"
                  v-model="form.email"
                  type="email"
                  autocomplete="username"
                  :placeholder="$t('auth.email_placeholder')"
                  required
                  autofocus
                  class="field-input"
                  :class="{ 'field-input--error': form.errors.email }"
                />
              </div>
              <InputError :message="form.errors.email" class="field-error" />
            </div>

            <!-- Password -->
            <div class="field">
              <div class="field-header">
                <label for="password" class="field-label">{{ $t('auth.password') }}</label>
              
              </div>
              <div class="field-wrap">
                <svg class="field-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <input
                  id="password"
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="current-password"
                  :placeholder="$t('auth.password_placeholder')"
                  required
                  class="field-input field-input--padded"
                  :class="{ 'field-input--error': form.errors.password }"
                />
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="eye-btn"
                  :aria-label="showPassword ? $t('auth.hide_password') : $t('auth.show_password')"
                >
                  <svg v-if="!showPassword" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                  <svg v-else fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                  </svg>
                </button>
              </div>
              <InputError :message="form.errors.password" class="field-error" />
            </div>

            <!-- Submit -->
            <button
              type="submit"
              class="submit-btn"
              :class="{ 'submit-btn--loading': form.processing }"
              :disabled="form.processing"
            >
              <span v-if="!form.processing" class="submit-inner">
                {{ $t('auth.sign_in') }}
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 8l4 4m0 0l-4 4m4-4H3" />
                </svg>
              </span>
              <span v-else class="submit-inner">
                <svg class="spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
                {{ $t('auth.signing_in') }}
              </span>
            </button>

          </form>

          <p class="copyright">© {{ currentYear }} {{ $t('auth.copyright') }}</p>
        </div>
      </div>
      </main>
    </div>
  </GuestLayout>
</template>

<style scoped>
.login-root {
  position: relative;
  min-height: 100vh;
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  background: radial-gradient(circle at 15% 15%, #1e1b4b 0%, #0a0a0f 45%, #0a0a0f 100%);
  font-family: 'Figtree', 'Inter', system-ui, sans-serif;
  padding: 24px;
}

.grid-bg {
  position: absolute; inset: 0;
  background-image:
    linear-gradient(rgba(255,255,255,0.035) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,0.035) 1px, transparent 1px);
  background-size: 40px 40px;
  pointer-events: none;
}

.orb { position: absolute; border-radius: 50%; pointer-events: none; filter: blur(10px); }
.orb-1 {
  top: -160px; left: -160px;
  width: 520px; height: 520px;
  background: radial-gradient(circle, rgba(99,102,241,0.28) 0%, transparent 65%);
}
.orb-2 {
  bottom: -140px; right: -120px;
  width: 480px; height: 480px;
  background: radial-gradient(circle, rgba(168,85,247,0.20) 0%, transparent 65%);
}

.center-wrap {
  position: relative;
  z-index: 2;
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.form-card {
  width: 100%;
  max-width: 400px;
  background: #fff;
  border-radius: 20px;
  padding: 40px 36px;
  box-shadow: 0 25px 70px -15px rgba(0,0,0,0.55), 0 0 0 1px rgba(255,255,255,0.06);
  animation: card-in 0.45s ease;
}
@keyframes card-in { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }

.logo-row { display: flex; align-items: center; gap: 12px; margin-bottom: 32px; }
.logo-icon {
  width: 38px; height: 38px; background: linear-gradient(135deg, #0a0a0f 0%, #1e1b4b 100%); border-radius: 10px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.logo-icon svg { width: 18px; height: 18px; stroke: #fff; }
.logo-name { font-size: 15px; font-weight: 700; color: #0a0a0f; letter-spacing: -0.01em; line-height: 1.2; }
.logo-sub { font-size: 11px; color: #9ca3af; font-weight: 400; margin-top: 1px; }

.form-title { font-size: 26px; font-weight: 700; color: #0a0a0f; letter-spacing: -0.025em; margin-bottom: 6px; }
.form-sub { font-size: 14px; color: #9ca3af; margin-bottom: 28px; }

.status-msg {
  display: flex; align-items: center; gap: 10px;
  background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;
  padding: 12px 16px; margin-bottom: 24px; font-size: 13px; font-weight: 500; color: #166534;
}
.status-icon { width: 16px; height: 16px; flex-shrink: 0; }

.login-form { display: flex; flex-direction: column; gap: 20px; }
.field { display: flex; flex-direction: column; gap: 8px; }
.field-header { display: flex; justify-content: space-between; align-items: center; }
.field-label { font-size: 11px; font-weight: 600; color: #374151; letter-spacing: 0.07em; text-transform: uppercase; }
.forgot-link { font-size: 12px; color: #6366f1; text-decoration: none; font-weight: 500; transition: color 0.15s; }
.forgot-link:hover { color: #4338ca; }
.field-wrap { position: relative; }
.field-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; stroke: #9ca3af; pointer-events: none; transition: stroke 0.15s; }
.field-input {
  width: 100%; height: 48px; border: 1.5px solid #e5e7eb; border-radius: 10px;
  padding: 0 14px 0 40px; font-size: 14px; color: #0a0a0f; background: #fafafa;
  font-family: inherit; outline: none; transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
}
.field-input::placeholder { color: #c2c6cf; }
.field-input:hover { border-color: #cbd0d9; }
.field-input:focus { border-color: #6366f1; background: #fff; box-shadow: 0 0 0 4px rgba(99,102,241,0.1); }
.field-input:focus + .eye-btn,
.field-wrap:focus-within .field-icon { stroke: #6366f1; }
.field-input--padded { padding-right: 44px; }
.field-input--error { border-color: #f87171; }
.field-input--error:focus { box-shadow: 0 0 0 4px rgba(248,113,113,0.12); }
.eye-btn {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer; padding: 4px; color: #9ca3af;
  display: flex; align-items: center; justify-content: center; border-radius: 6px; transition: color 0.15s, background 0.15s;
}
.eye-btn:hover { color: #6b7280; background: #f3f4f6; }
.eye-btn svg { width: 17px; height: 17px; }
.field-error { font-size: 12px; color: #ef4444; margin-top: -4px; }

.submit-btn {
  width: 100%; height: 48px; background: linear-gradient(135deg, #0a0a0f 0%, #1e1b4b 100%); border: none; border-radius: 10px;
  color: #fff; font-size: 14px; font-weight: 600; font-family: inherit; cursor: pointer;
  letter-spacing: 0.01em; transition: box-shadow 0.2s, transform 0.1s, filter 0.2s;
  margin-top: 4px; box-shadow: 0 2px 8px rgba(10,10,15,0.2);
}
.submit-btn:hover:not(:disabled) { filter: brightness(1.15); box-shadow: 0 6px 20px rgba(30,27,75,0.35); transform: translateY(-1px); }
.submit-btn:active:not(:disabled) { transform: translateY(0); }
.submit-btn:focus-visible { outline: none; box-shadow: 0 0 0 4px rgba(99,102,241,0.25), 0 2px 8px rgba(10,10,15,0.2); }
.submit-btn--loading, .submit-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.submit-inner { display: flex; align-items: center; justify-content: center; gap: 8px; }
.submit-inner svg { width: 16px; height: 16px; }
.spin { animation: spin 0.9s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

.copyright { text-align: center; font-size: 12px; color: #d1d5db; margin-top: 28px; }

@media (max-width: 480px) {
  .login-root { padding: 16px; }
  .form-card { padding: 32px 22px; border-radius: 16px; }
  .form-title { font-size: 22px; }
  .logo-row { margin-bottom: 26px; }
}

.login-root {
  min-height: 100svh;
  padding: 40px 24px;
  overflow: auto;
  background:
    radial-gradient(ellipse at 12% 10%, rgba(201, 211, 183, 0.3), transparent 36%),
    #f4f4ee;
}

.login-shell {
  display: grid;
  grid-template-columns: minmax(320px, 0.9fr) minmax(420px, 1.1fr);
  width: min(100%, 1060px);
  min-height: 660px;
  overflow: hidden;
  border: 1px solid rgba(51, 65, 48, 0.08);
  border-radius: 28px;
  background: #fffefa;
  box-shadow: 0 32px 90px -42px rgba(37, 49, 35, 0.32), 0 8px 26px rgba(37, 49, 35, 0.06);
}

.brand-panel {
  position: relative;
  display: flex;
  min-height: 660px;
  flex-direction: column;
  justify-content: center;
  overflow: hidden;
  padding: 38px;
  background:
    radial-gradient(ellipse at 18% 82%, rgba(132, 157, 111, 0.26), transparent 47%),
    linear-gradient(150deg, #263d32 0%, #304c3c 54%, #3b5944 100%);
}

.brand-panel::before,
.brand-panel::after {
  position: absolute;
  right: -210px;
  width: 360px;
  height: 360px;
  border: 1px solid rgba(242, 240, 218, 0.1);
  border-radius: 50%;
  content: "";
}

.brand-panel::before { top: 15%; }
.brand-panel::after { top: 21%; right: -250px; width: 300px; height: 300px; }

.brand-panel-glow {
  position: absolute;
  top: 30%;
  left: -160px;
  width: 380px;
  height: 380px;
  border-radius: 50%;
  background: rgba(160, 177, 129, 0.09);
}

.botanical-art {
  position: absolute;
  top: 50%;
  left: 50%;
  width: min(92%, 380px);
  transform: translate(-50%, -50%);
}

.botanical-stem,
.botanical-vein {
  stroke: rgba(225, 233, 206, 0.58);
  stroke-width: 1.5;
  stroke-linecap: round;
}

.botanical-leaf {
  fill: rgba(190, 207, 159, 0.12);
  stroke: rgba(225, 233, 206, 0.62);
  stroke-width: 1.5;
  stroke-linejoin: round;
}

.botanical-vein {
  stroke-width: 1;
  opacity: 0.7;
}

.brand-panel-footer {
  position: absolute;
  right: 38px;
  bottom: 34px;
  left: 38px;
  display: flex;
  align-items: center;
  gap: 12px;
  color: rgba(235, 239, 222, 0.67);
  font-size: 11px;
  letter-spacing: 0.09em;
  text-transform: uppercase;
}

.brand-panel-footer span {
  width: 28px;
  height: 1px;
  background: #c6d3ad;
}

.center-wrap {
  min-width: 0;
  padding: 48px;
  background:
    radial-gradient(ellipse at 100% 0%, rgba(226, 231, 210, 0.23), transparent 38%),
    #fffefa;
}

.form-card {
  max-width: 390px;
  padding: 28px 0;
  border-radius: 0;
  background: transparent;
  box-shadow: none;
  animation: card-in 0.45s ease both;
}

.logo-row { margin-bottom: 42px; }

.logo-icon {
  width: 44px;
  height: 44px;
  border: 1px solid rgba(242, 240, 218, 0.2);
  border-radius: 15px;
  background: linear-gradient(145deg, #304c3c, #526e49);
}

.logo-icon svg { width: 21px; height: 21px; }
.language-toggle {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 36px;
  margin-left: auto;
  padding: 0 11px;
  border: 1px solid #e8e9e0;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.7);
  color: #a0a496;
  font: inherit;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  transition: border-color 0.18s, background 0.18s;
}

.language-toggle:hover { border-color: #bcc7ad; background: #fff; }
.language-active { color: #405b3d; }
.language-divider { color: #d5d8ce; font-weight: 400; }
.logo-name { color: #27372c; font-size: 16px; }
.logo-sub { color: #8b9485; }

.form-title {
  margin-bottom: 9px;
  color: #27372c;
  font-size: clamp(26px, 3vw, 32px);
  letter-spacing: -0.045em;
}

.form-sub { margin-bottom: 30px; color: #8b9185; line-height: 1.6; }

.field-label {
  color: #455046;
  font-size: 12px;
  letter-spacing: 0.015em;
  text-transform: none;
}

.login-form { gap: 21px; }
.field { gap: 9px; }

.field-icon {
  left: 15px;
  width: 17px;
  height: 17px;
  stroke: #9ba397;
}

.field-input {
  height: 52px;
  padding-left: 44px;
  border: 1px solid #e5e7df;
  border-radius: 12px;
  background: #fbfcf8;
  color: #29372d;
  transition: border-color 0.16s, background 0.16s, box-shadow 0.16s;
}

.field-input::placeholder { color: #b2b8ad; }
.field-input:hover { border-color: #cbd3c3; }
.field-input:focus {
  border-color: #81966d;
  background: #fff;
  box-shadow: 0 0 0 4px rgba(129, 150, 109, 0.13);
}

.field-wrap:focus-within .field-icon { stroke: #6f875c; }
.field-input--padded { padding-right: 48px; }
.field-input--error { border-color: #e48b7e; }
.field-input--error:focus { box-shadow: 0 0 0 4px rgba(228, 139, 126, 0.14); }

.eye-btn { right: 11px; padding: 6px; color: #929b8e; }
.eye-btn:hover { background: #eef1e9; color: #52694a; }
.eye-btn svg { width: 18px; height: 18px; }
.field-error { margin-top: -2px; color: #c45f52; }

.status-msg {
  margin-bottom: 22px;
  border-color: #d5e5ce;
  border-radius: 12px;
  background: #f2f8ef;
  color: #456b42;
}

.submit-btn {
  height: 52px;
  margin-top: 5px;
  border-radius: 12px;
  background: linear-gradient(105deg, #405b3d, #526e49);
  box-shadow: 0 7px 16px rgba(64, 91, 61, 0.16);
  transition: box-shadow 0.2s, transform 0.15s, filter 0.2s;
}

.submit-btn:hover:not(:disabled) {
  filter: brightness(1.07);
  box-shadow: 0 10px 22px rgba(64, 91, 61, 0.24);
  transform: translateY(-1px);
}

.submit-btn:focus-visible {
  outline: 3px solid rgba(94, 123, 80, 0.3);
  outline-offset: 3px;
}

.copyright { margin-top: 34px; color: #a6aa9e; font-size: 11px; line-height: 1.5; }
.language-toggle:focus-visible,
.eye-btn:focus-visible {
  outline: 3px solid rgba(94, 123, 80, 0.25);
  outline-offset: 2px;
}

@media (max-width: 760px) {
  .login-root { align-items: flex-start; padding: 20px 14px; }
  .login-shell {
    grid-template-columns: 1fr;
    width: min(100%, 520px);
    min-height: 0;
    border-radius: 23px;
  }
  .brand-panel { min-height: 164px; padding: 25px 26px; }
  .botanical-art {
    top: 48%;
    right: -14px;
    left: auto;
    width: 190px;
    transform: translateY(-50%);
    opacity: 0.56;
  }
  .brand-panel::before { top: -115px; right: -140px; width: 330px; height: 330px; }
  .brand-panel::after { top: -80px; right: -105px; width: 260px; height: 260px; }
  .brand-panel-footer { display: none; }
  .center-wrap { padding: 25px 34px 22px; }
  .form-card { padding: 18px 0 12px; }
  .logo-row { margin-bottom: 35px; }
}

@media (max-width: 420px) {
  .login-root { padding: 0; background: #fffefa; }
  .login-shell {
    width: 100%;
    min-height: 100svh;
    border: 0;
    border-radius: 0;
    box-shadow: none;
  }
  .brand-panel { min-height: 150px; padding: 22px; }
  .center-wrap { padding: 22px 24px 20px; }
  .form-card { padding-top: 12px; }
  .form-title { font-size: 24px; }
  .copyright { margin-top: 28px; }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}
</style>
