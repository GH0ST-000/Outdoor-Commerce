export type Locale = "en" | "ka";

export const locales: Locale[] = ["en", "ka"];

export const localeLabels: Record<Locale, string> = {
  en: "English",
  ka: "ქართული",
};

type Dictionary = {
  brand: string;
  home: {
    headline: string;
    copy: string;
    createAccount: string;
    signIn: string;
    healthReady: string;
  };
  common: {
    language: string;
    theme: string;
    light: string;
    dark: string;
    system: string;
    loadingSession: string;
    redirectSignIn: string;
    redirectAccount: string;
  };
  auth: {
    signInTitle: string;
    signInLead: string;
    email: string;
    password: string;
    showPassword: string;
    hidePassword: string;
    rememberMe: string;
    signingIn: string;
    signIn: string;
    createAccountLink: string;
    forgotPasswordLink: string;
    registerTitle: string;
    registerLead: string;
    firstName: string;
    lastName: string;
    confirmPassword: string;
    creatingAccount: string;
    createAccount: string;
    alreadyHaveAccount: string;
    forgotTitle: string;
    forgotLead: string;
    sendReset: string;
    sending: string;
    forgotSuccess: string;
    backToSignIn: string;
    resetTitle: string;
    resetLead: string;
    newPassword: string;
    confirmNewPassword: string;
    updatePassword: string;
    updating: string;
    resetSuccess: string;
    verifyTitle: string;
    verifySuccess: string;
    verifyAlready: string;
    verifyError: string;
    verifyUnknown: string;
    goToAccount: string;
    unableSignIn: string;
    unableRegister: string;
    unableForgot: string;
    unableReset: string;
  };
  account: {
    title: string;
    verified: string;
    unverified: string;
    resendVerification: string;
    sending: string;
    resendSuccess: string;
    resendThrottle: string;
    unableResend: string;
    signOut: string;
    signingOut: string;
    unableSignOut: string;
    visualCopy: string;
  };
  authShell: {
    visualCopy: string;
  };
};

export const dictionaries: Record<Locale, Dictionary> = {
  en: {
    brand: "Outdoor Commerce",
    home: {
      headline: "Gear for the hunt, the river, and the long way out.",
      copy: "A refined storefront for hunting, fishing, and outdoor equipment — built for people who choose carefully.",
      createAccount: "Create account",
      signIn: "Sign in",
      healthReady: "Frontend health: ready",
    },
    common: {
      language: "Language",
      theme: "Theme",
      light: "Light",
      dark: "Dark",
      system: "System",
      loadingSession: "Checking your session…",
      redirectSignIn: "Redirecting to sign in…",
      redirectAccount: "Redirecting to your account…",
    },
    auth: {
      signInTitle: "Sign in",
      signInLead: "Access your Outdoor Commerce account.",
      email: "Email",
      password: "Password",
      showPassword: "Show password",
      hidePassword: "Hide password",
      rememberMe: "Remember me on this device",
      signingIn: "Signing in…",
      signIn: "Sign in",
      createAccountLink: "Create an account",
      forgotPasswordLink: "Forgot password?",
      registerTitle: "Create account",
      registerLead:
        "Register to manage your Outdoor Commerce customer account.",
      firstName: "First name",
      lastName: "Last name",
      confirmPassword: "Confirm password",
      creatingAccount: "Creating account…",
      createAccount: "Create account",
      alreadyHaveAccount: "Already have an account? Sign in",
      forgotTitle: "Forgot password",
      forgotLead:
        "If an account exists for that email, we will send reset instructions.",
      sendReset: "Send reset link",
      sending: "Sending…",
      forgotSuccess:
        "If an account exists for that email, password reset instructions have been sent.",
      backToSignIn: "Back to sign in",
      resetTitle: "Reset password",
      resetLead: "Choose a new password for your account.",
      newPassword: "New password",
      confirmNewPassword: "Confirm new password",
      updatePassword: "Update password",
      updating: "Updating…",
      resetSuccess:
        "Your password was updated. You can sign in with the new password.",
      verifyTitle: "Email verification",
      verifySuccess: "Your email address has been verified.",
      verifyAlready: "Your email address was already verified.",
      verifyError:
        "We could not verify that link. It may be invalid or expired.",
      verifyUnknown:
        "Check your inbox for a verification link, or open Account to resend one.",
      goToAccount: "Go to account",
      unableSignIn: "Unable to sign in. Please try again.",
      unableRegister: "Unable to create your account. Please try again.",
      unableForgot: "Unable to process that request. Please try again.",
      unableReset: "Unable to reset your password. Please try again.",
    },
    account: {
      title: "Your account",
      verified: "Email verified.",
      unverified:
        "Your email is not verified yet. Check your inbox, or request another message.",
      resendVerification: "Resend verification email",
      sending: "Sending…",
      resendSuccess:
        "If verification is still pending, a new email has been sent.",
      resendThrottle:
        "Please wait before requesting another verification email.",
      unableResend: "Unable to resend verification email.",
      signOut: "Sign out",
      signingOut: "Signing out…",
      unableSignOut: "Unable to sign out. Please try again.",
      visualCopy:
        "Your profile stays light — name, email, and verification. Nothing more until catalog and checkout arrive.",
    },
    authShell: {
      visualCopy:
        "Quiet gear for hard country — accounts that stay out of the way until you need them.",
    },
  },
  ka: {
    brand: "Outdoor Commerce",
    home: {
      headline: "აღჭურვილობა ნადირობისთვის, მდინარისთვის და გრძელი გზისთვის.",
      copy: "განახლებული ვიტრინა ნადირობის, თევზაობისა და კამპინგის აღჭურვილობისთვის — მათთვის, ვინც ფრთხილად ირჩევს.",
      createAccount: "ანგარიშის შექმნა",
      signIn: "შესვლა",
      healthReady: "ფრონტის ჯანმრთელობა: მზადაა",
    },
    common: {
      language: "ენა",
      theme: "თემა",
      light: "ღია",
      dark: "მუქი",
      system: "სისტემური",
      loadingSession: "სესიის შემოწმება…",
      redirectSignIn: "გადამისამართება შესვლაზე…",
      redirectAccount: "გადამისამართება ანგარიშზე…",
    },
    auth: {
      signInTitle: "შესვლა",
      signInLead: "შედით თქვენს Outdoor Commerce ანგარიშზე.",
      email: "ელფოსტა",
      password: "პაროლი",
      showPassword: "პაროლის ჩვენება",
      hidePassword: "პაროლის დამალვა",
      rememberMe: "დამახსოვრება ამ მოწყობილობაზე",
      signingIn: "შესვლა…",
      signIn: "შესვლა",
      createAccountLink: "ანგარიშის შექმნა",
      forgotPasswordLink: "დაგავიწყდათ პაროლი?",
      registerTitle: "ანგარიშის შექმნა",
      registerLead:
        "დარეგისტრირდით Outdoor Commerce მომხმარებლის ანგარიშის სამართავად.",
      firstName: "სახელი",
      lastName: "გვარი",
      confirmPassword: "პაროლის დადასტურება",
      creatingAccount: "ანგარიში იქმნება…",
      createAccount: "ანგარიშის შექმნა",
      alreadyHaveAccount: "უკვე გაქვთ ანგარიში? შესვლა",
      forgotTitle: "პაროლის აღდგენა",
      forgotLead:
        "თუ ანგარიში არსებობს ამ ელფოსტაზე, გამოგიგზავნით აღდგენის ინსტრუქციას.",
      sendReset: "ბმულის გაგზავნა",
      sending: "იგზავნება…",
      forgotSuccess:
        "თუ ანგარიში არსებობს ამ ელფოსტაზე, პაროლის აღდგენის ინსტრუქცია გაიგზავნა.",
      backToSignIn: "უკან შესვლაზე",
      resetTitle: "ახალი პაროლი",
      resetLead: "აირჩიეთ ახალი პაროლი თქვენი ანგარიშისთვის.",
      newPassword: "ახალი პაროლი",
      confirmNewPassword: "ახალი პაროლის დადასტურება",
      updatePassword: "პაროლის განახლება",
      updating: "ახლდება…",
      resetSuccess: "პაროლი განახლდა. შეგიძლიათ შეხვიდეთ ახალი პაროლით.",
      verifyTitle: "ელფოსტის დადასტურება",
      verifySuccess: "თქვენი ელფოსტა დადასტურებულია.",
      verifyAlready: "თქვენი ელფოსტა უკვე დადასტურებული იყო.",
      verifyError:
        "ბმულის დადასტურება ვერ მოხერხდა. შესაძლოა არასწორი ან ვადაგასულია.",
      verifyUnknown:
        "შეამოწმეთ შემოსული წერილები, ან ანგარიშიდან ხელახლა გამოაგზავნეთ ბმული.",
      goToAccount: "ანგარიშზე გადასვლა",
      unableSignIn: "შესვლა ვერ მოხერხდა. სცადეთ თავიდან.",
      unableRegister: "ანგარიშის შექმნა ვერ მოხერხდა. სცადეთ თავიდან.",
      unableForgot: "მოთხოვნის დამუშავება ვერ მოხერხდა. სცადეთ თავიდან.",
      unableReset: "პაროლის აღდგენა ვერ მოხერხდა. სცადეთ თავიდან.",
    },
    account: {
      title: "თქვენი ანგარიში",
      verified: "ელფოსტა დადასტურებულია.",
      unverified:
        "ელფოსტა ჯერ არ არის დადასტურებული. შეამოწმეთ შემოსული, ან მოითხოვეთ ახალი წერილი.",
      resendVerification: "დადასტურების ხელახლა გაგზავნა",
      sending: "იგზავნება…",
      resendSuccess:
        "თუ დადასტურება ჯერ კიდევ ელოდება, ახალი წერილი გაიგზავნა.",
      resendThrottle: "გთხოვთ დაიცადოთ ახალი დადასტურების მოთხოვნამდე.",
      unableResend: "დადასტურების გაგზავნა ვერ მოხერხდა.",
      signOut: "გასვლა",
      signingOut: "გასვლა…",
      unableSignOut: "გასვლა ვერ მოხერხდა. სცადეთ თავიდან.",
      visualCopy:
        "პროფილი მსუბუქია — სახელი, ელფოსტა და დადასტურება. მეტი კატალოგისა და შეკვეთის შემდეგ.",
    },
    authShell: {
      visualCopy:
        "მშვიდი აღჭურვილობა რთული ადგილებისთვის — ანგარიშები, რომლებიც გზას არ უშლის, სანამ არ დაგჭირდებათ.",
    },
  },
};
