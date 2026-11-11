const { useState, useEffect, useRef } = React;
const ACCESS_REQUESTS_KEY = 'law-office-document-access-requests';
const portalData = window.CLIENT_PORTAL_DATA || {};
const CLIENT_THEMES = [
  { id: 'amber', name: 'Classic Amber', color: '#f59e0b' },
  { id: 'ocean', name: 'Ocean Blue', color: '#38bdf8' },
  { id: 'emerald', name: 'Emerald', color: '#34d399' },
];

function readDocumentAccessRequests() {
  const savedRequests = window.localStorage.getItem(ACCESS_REQUESTS_KEY);
  if (!savedRequests) return [];
  try {
    const requests = JSON.parse(savedRequests);
    return Array.isArray(requests) ? requests : [];
  } catch (error) {
    console.error('Unable to read document access requests from browser storage.', error);
    return [];
  }
}

function ClientPortal() {
  const currentView = 'dashboard';
  const [isScheduleModalOpen, setIsScheduleModalOpen] = useState(portalData.appointmentNoticeType === 'error');
  const [selectedDocument, setSelectedDocument] = useState(null);
  const [documentRequestNotice, setDocumentRequestNotice] = useState('');
  const [documentAccessRequests, setDocumentAccessRequests] = useState(readDocumentAccessRequests);
  const [activeHeaderPopover, setActiveHeaderPopover] = useState(null);
  const [profileView, setProfileView] = useState('menu');
  const [theme, setTheme] = useState(document.documentElement.dataset.clientTheme || 'amber');
  const [themeStorageError, setThemeStorageError] = useState(false);
  const headerActionsRef = useRef(null);
  const clientProfile = portalData.profile;
  const activeCases = portalData.activeCases;
  const upcomingAppointments = portalData.upcomingAppointments;
  const [caseDocuments, setCaseDocuments] = useState(portalData.documents);

  // State for the Appointment Form
  const [appointmentFormData, setAppointmentFormData] = useState({
    legal_matter_type: portalData.appointmentFormValues?.matter_type || 'Family Law',
    other_matter: portalData.appointmentFormValues?.other_matter || '',
    appointment_date: portalData.appointmentFormValues?.appointment_date || '',
    preferred_attorney: portalData.appointmentFormValues?.preferred_attorney || '',
    issue_description: portalData.appointmentFormValues?.issue_description || '',
    confirmed: portalData.appointmentFormValues?.confirmed || false,
  });

  useEffect(() => {
    if (window.lucide) {
      window.lucide.createIcons();
    }
  }, [isScheduleModalOpen, selectedDocument, documentAccessRequests]);

  useEffect(() => {
    if (!activeHeaderPopover) return undefined;
    const closeOnOutsideClick = (event) => {
      if (!headerActionsRef.current?.contains(event.target)) {
        setActiveHeaderPopover(null);
      }
    };
    const closeOnEscape = (event) => {
      if (event.key === 'Escape') setActiveHeaderPopover(null);
    };
    document.addEventListener('mousedown', closeOnOutsideClick);
    document.addEventListener('keydown', closeOnEscape);
    return () => {
      document.removeEventListener('mousedown', closeOnOutsideClick);
      document.removeEventListener('keydown', closeOnEscape);
    };
  }, [activeHeaderPopover]);

  const changeTheme = (nextTheme) => {
    setTheme(nextTheme);
    document.documentElement.dataset.clientTheme = nextTheme;
    setThemeStorageError(false);
    try {
      window.localStorage.setItem(window.CLIENT_PORTAL_THEME_STORAGE_KEY, nextTheme);
    } catch (error) {
      console.error('Unable to save the Client portal theme.', error);
      setThemeStorageError(true);
    }
  };

  useEffect(() => {
    const syncDocumentRequests = (event) => {
      if (event.key !== ACCESS_REQUESTS_KEY) return;
      setDocumentAccessRequests(readDocumentAccessRequests());
    };
    window.addEventListener('storage', syncDocumentRequests);
    return () => window.removeEventListener('storage', syncDocumentRequests);
  }, []);

  useEffect(() => {
    if (!selectedDocument) return undefined;
    const preventPrint = (event) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'p') {
        event.preventDefault();
      }
      if (event.key === 'Escape') {
        setSelectedDocument(null);
      }
    };
    window.addEventListener('keydown', preventPrint);
    return () => window.removeEventListener('keydown', preventPrint);
  }, [selectedDocument]);

  const handleAccessRequest = (documentId) => {
    const document = caseDocuments.find((doc) => doc.document_id === documentId);
    if (!document) return;
    const request = {
      request_id: `${clientProfile.email}-${document.document_id}`,
      document_id: document.document_id,
      document_name: document.document_name,
      category: document.category,
      client_name: clientProfile.fullname,
      client_email: clientProfile.email,
      requested_at: new Date().toISOString(),
      status: 'Pending'
    };
    const updatedRequests = [
      ...documentAccessRequests.filter((item) => item.request_id !== request.request_id),
      request
    ];
    try {
      window.localStorage.setItem(ACCESS_REQUESTS_KEY, JSON.stringify(updatedRequests));
      setDocumentAccessRequests(updatedRequests);
      setDocumentRequestNotice('Your access request was sent to the Staff portal.');
    } catch (error) {
      console.error('Unable to save the document access request.', error);
      setDocumentRequestNotice('Unable to send the request. Please check browser storage and try again.');
    }
  };

  // --- 1. LOGIN VIEW ---
  if (currentView === 'login') {
    return (
      <div className="min-h-screen bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-950 via-[#030712] to-black flex items-center justify-center p-4">
        <div className="max-w-md w-full bg-slate-900/85 backdrop-blur-md rounded-2xl shadow-2xl border border-amber-500/20 p-8">
          <div className="text-center mb-8">
            <div className="inline-flex p-3 bg-amber-500/10 text-amber-400 rounded-full mb-3 border border-amber-500/30">
              <i data-lucide="shield-check" className="w-8 h-8"></i>
            </div>
            <h1 className="text-2xl font-bold text-white tracking-wide">Law Office Portal</h1>
            <p className="text-slate-400 text-sm mt-1">Sign in to access your legal cases</p>
          </div>

          <form onSubmit={(e) => { e.preventDefault(); setCurrentView('dashboard'); }} className="space-y-4">
            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-amber-400/80 mb-1">Email Address</label>
              <div className="relative">
                <span className="absolute left-3 top-3 text-slate-500"><i data-lucide="mail" className="w-5 h-5"></i></span>
                <input 
                  type="email" 
                  required 
                  defaultValue="jane@example.com"
                  className="w-full bg-slate-950/60 border border-slate-800 rounded-lg pl-10 pr-4 py-2.5 text-white text-sm focus:outline-none focus:border-amber-500 transition" 
                  placeholder="client@example.com" 
                />
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-amber-400/80 mb-1">Password</label>
              <div className="relative">
                <span className="absolute left-3 top-3 text-slate-500"><i data-lucide="lock" className="w-5 h-5"></i></span>
                <input 
                  type="password" 
                  required 
                  defaultValue="password123"
                  className="w-full bg-slate-950/60 border border-slate-800 rounded-lg pl-10 pr-4 py-2.5 text-white text-sm focus:outline-none focus:border-amber-500 transition" 
                  placeholder="••••••••••••" 
                />
              </div>
            </div>

            <button 
              type="submit" 
              className="w-full mt-2 bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-500 hover:to-amber-400 text-slate-950 font-bold py-2.5 rounded-lg transition duration-200 flex items-center justify-center gap-2">
              Log In <i data-lucide="arrow-right" className="w-4 h-4"></i>
            </button>
          </form>

          <div className="text-center mt-6">
            <p className="text-sm text-slate-400">
              Don't have an account?{' '}
              <button onClick={() => setCurrentView('signup')} className="text-amber-400 hover:underline font-medium">
                Sign up for portal access
              </button>
            </p>
          </div>
        </div>
      </div>
    );
  }

  // --- 2. SIGNUP VIEW ---
  if (currentView === 'signup') {
    return (
      <div className="min-h-screen bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-950 via-[#030712] to-black flex items-center justify-center p-4">
        <div className="max-w-lg w-full bg-slate-900/85 backdrop-blur-md rounded-2xl shadow-2xl border border-amber-500/20 p-8">
          <div className="text-center mb-6">
            <div className="inline-flex p-3 bg-amber-500/10 text-amber-400 rounded-full mb-3 border border-amber-500/30">
              <i data-lucide="user" className="w-8 h-8"></i>
            </div>
            <h1 className="text-2xl font-bold text-white tracking-wide">Create Client Portal Account</h1>
            <p className="text-slate-400 text-sm mt-1">Register to connect with your legal representation</p>
          </div>

          <form onSubmit={(e) => { e.preventDefault(); setCurrentView('dashboard'); }} className="space-y-4">
            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-amber-400/80 mb-1">Full Name / Company Name</label>
              <div className="relative">
                <span className="absolute left-3 top-3 text-slate-500"><i data-lucide="user" className="w-5 h-5"></i></span>
                <input 
                  type="text" 
                  required 
                  defaultValue={clientProfile.fullname}
                  onChange={(e) => setClientProfile({...clientProfile, fullname: e.target.value})}
                  className="w-full bg-slate-950/60 border border-slate-800 rounded-lg pl-10 pr-4 py-2 text-white text-sm focus:outline-none focus:border-amber-500 transition" 
                  placeholder="Jane Doe" 
                />
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold uppercase tracking-wider text-amber-400/80 mb-1">Email Address</label>
                <div className="relative">
                  <span className="absolute left-3 top-3 text-slate-500"><i data-lucide="mail" className="w-5 h-5"></i></span>
                  <input 
                    type="email" 
                    required 
                    defaultValue={clientProfile.email}
                    className="w-full bg-slate-950/60 border border-slate-800 rounded-lg pl-10 pr-4 py-2 text-white text-sm focus:outline-none focus:border-amber-500 transition" 
                    placeholder="jane@example.com" 
                  />
                </div>
              </div>
              <div>
                <label className="block text-xs font-semibold uppercase tracking-wider text-amber-400/80 mb-1">Phone Number</label>
                <div className="relative">
                  <span className="absolute left-3 top-3 text-slate-500"><i data-lucide="phone" className="w-5 h-5"></i></span>
                  <input 
                    type="text" 
                    required 
                    defaultValue={clientProfile.phone}
                    className="w-full bg-slate-950/60 border border-slate-800 rounded-lg pl-10 pr-4 py-2 text-white text-sm focus:outline-none focus:border-amber-500 transition" 
                    placeholder="+1 (555) 000-0000" 
                  />
                </div>
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-amber-400/80 mb-1">Physical Address</label>
              <div className="relative">
                <span className="absolute left-3 top-3 text-slate-500"><i data-lucide="map-pin" className="w-5 h-5"></i></span>
                <input 
                  type="text" 
                  required 
                  defaultValue={clientProfile.address}
                  className="w-full bg-slate-950/60 border border-slate-800 rounded-lg pl-10 pr-4 py-2 text-white text-sm focus:outline-none focus:border-amber-500 transition" 
                  placeholder="123 Legal Way, City, ST" 
                />
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-amber-400/80 mb-1">Password</label>
              <div className="relative">
                <span className="absolute left-3 top-3 text-slate-500"><i data-lucide="lock" className="w-5 h-5"></i></span>
                <input 
                  type="password" 
                  required 
                  className="w-full bg-slate-950/60 border border-slate-800 rounded-lg pl-10 pr-4 py-2 text-white text-sm focus:outline-none focus:border-amber-500 transition" 
                  placeholder="••••••••••••" 
                />
              </div>
            </div>

            <button 
              type="submit" 
              className="w-full mt-4 bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-500 hover:to-amber-400 text-slate-950 font-bold py-2.5 rounded-lg transition duration-200">
              Create Account & Access Portal
            </button>
          </form>

          <div className="text-center mt-6">
            <p className="text-sm text-slate-400">
              Already have an account?{' '}
              <button onClick={() => setCurrentView('login')} className="text-amber-400 hover:underline font-medium">
                Log in here
              </button>
            </p>
          </div>
        </div>
      </div>
    );
  }

  // --- 3. CLIENT DASHBOARD VIEW ---
  return (
    <div className="min-h-screen bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-950 via-[#030712] to-black text-slate-100">
      <header className="bg-slate-900/90 backdrop-blur-md border-b border-amber-500/20 px-4 sm:px-6 py-4 flex justify-between items-center shadow-lg sticky top-0 z-50">
        <div className="flex items-center space-x-3">
          <div className="p-2 bg-gradient-to-br from-amber-500 to-amber-700 rounded-lg text-slate-950">
            <i data-lucide="shield-check" className="w-6 h-6"></i>
          </div>
          <div className="min-w-0">
            <span className="font-bold text-lg tracking-wide text-white">Law Office Management System</span>
            <span className="block text-xs text-amber-400 font-semibold tracking-wider">CLIENT PORTAL</span>
          </div>
        </div>
        <div ref={headerActionsRef} className="relative flex shrink-0 items-center gap-2">
          <button
            type="button"
            aria-label="Notifications"
            aria-expanded={activeHeaderPopover === 'notifications'}
            aria-controls="client-notifications-popover"
            onClick={() => setActiveHeaderPopover((current) => current === 'notifications' ? null : 'notifications')}
            className="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-700 bg-slate-800 text-slate-300 transition hover:border-amber-500/40 hover:text-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5">
              <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
              <path d="M10 21h4"></path>
            </svg>
          </button>
          <button
            type="button"
            aria-label={`Open profile menu for ${clientProfile.fullname}`}
            aria-expanded={activeHeaderPopover === 'profile'}
            aria-controls="client-profile-popover"
            onClick={() => {
              setProfileView('menu');
              setActiveHeaderPopover((current) => current === 'profile' ? null : 'profile');
            }}
            className="flex h-10 w-10 items-center justify-center rounded-xl border border-amber-500/30 bg-amber-500/10 text-amber-300 transition hover:bg-amber-500/20 focus:outline-none focus:ring-2 focus:ring-amber-500">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5">
              <circle cx="12" cy="8" r="5"></circle>
              <path d="M20 21a8 8 0 0 0-16 0"></path>
            </svg>
          </button>

          {activeHeaderPopover === 'notifications' && (
            <section
              id="client-notifications-popover"
              aria-label="Notifications"
              className="absolute right-0 top-full z-50 mt-2 w-72 rounded-2xl border border-slate-700 bg-slate-900 p-4 shadow-2xl">
              <h2 className="text-sm font-semibold text-white">Notifications</h2>
              <p role="status" className="mt-3 rounded-xl border border-slate-800 bg-slate-950/60 p-4 text-center text-sm text-slate-400">
                You’re all caught up. New updates will appear here.
              </p>
            </section>
          )}

          {activeHeaderPopover === 'profile' && (
            <section
              id="client-profile-popover"
              aria-label="Profile and customization"
              className="absolute right-0 top-full z-50 mt-2 w-[min(20rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-700 bg-slate-900 shadow-2xl">
              <div className="border-b border-slate-800 p-4">
                {profileView !== 'menu' && (
                  <button
                    type="button"
                    onClick={() => setProfileView('menu')}
                    className="mb-3 flex items-center gap-2 text-xs font-medium text-slate-400 transition hover:text-white focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4">
                      <path d="m15 18-6-6 6-6"></path>
                      <path d="M9 12h12"></path>
                    </svg>
                    Profile menu
                  </button>
                )}
                <p className="truncate text-sm font-semibold text-white">{clientProfile.fullname}</p>
                <p className="truncate text-xs text-slate-400">{clientProfile.email}</p>
              </div>
              {profileView === 'menu' && (
                <div className="space-y-2 p-4">
                  <button
                    type="button"
                    onClick={() => setProfileView('account-settings')}
                    className="flex w-full items-center gap-3 rounded-xl border border-slate-800 bg-slate-950/50 px-3 py-3 text-left transition hover:border-slate-600 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4 text-amber-400">
                      <circle cx="12" cy="8" r="5"></circle>
                      <path d="M20 21a8 8 0 0 0-16 0"></path>
                    </svg>
                    <span className="text-sm font-medium text-slate-200">Account Settings</span>
                  </button>
                  <button
                    type="button"
                    onClick={() => setProfileView('customization')}
                    className="flex w-full items-center gap-3 rounded-xl border border-slate-800 bg-slate-950/50 px-3 py-3 text-left transition hover:border-slate-600 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4 text-amber-400">
                      <path d="m14 6 7 7-4 4-7-7"></path>
                      <path d="M3 21c3-1 5-2 7-5l-3-3c-3 2-4 4-4 8Z"></path>
                    </svg>
                    <span className="text-sm font-medium text-slate-200">Customization</span>
                  </button>
                </div>
              )}
              {profileView === 'account-settings' && (
                <div className="p-4">
                  <h2 className="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-300">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4 text-amber-400">
                      <circle cx="12" cy="8" r="5"></circle>
                      <path d="M20 21a8 8 0 0 0-16 0"></path>
                    </svg>
                    Account Settings
                  </h2>
                  <dl className="space-y-2">
                    {[
                      ['Full Name', clientProfile.fullname],
                      ['Username', clientProfile.username],
                      ['Email', clientProfile.email],
                      ['Phone', clientProfile.phone],
                      ['Address', clientProfile.address],
                    ].map(([label, value]) => (
                      <div key={label} className="rounded-lg border border-slate-800 bg-slate-950/50 px-3 py-2">
                        <dt className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{label}</dt>
                        <dd className="mt-1 break-words text-xs font-medium text-slate-200">{value || 'Not provided'}</dd>
                      </div>
                    ))}
                  </dl>
                </div>
              )}
              {profileView === 'customization' && (
              <div className="p-4">
                <h2 className="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-300">
                  <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4 text-amber-400">
                    <path d="m14 6 7 7-4 4-7-7"></path>
                    <path d="M3 21c3-1 5-2 7-5l-3-3c-3 2-4 4-4 8Z"></path>
                  </svg>
                  Customization
                </h2>
                <div className="space-y-2" aria-label="Choose an accent theme">
                  {CLIENT_THEMES.map((option) => (
                    <button
                      key={option.id}
                      type="button"
                      aria-pressed={theme === option.id}
                      onClick={() => changeTheme(option.id)}
                      className={`flex w-full items-center justify-between rounded-xl border px-3 py-2.5 text-left transition focus:outline-none focus:ring-2 focus:ring-amber-500 ${
                        theme === option.id
                          ? 'border-amber-500/40 bg-amber-500/10'
                          : 'border-slate-800 bg-slate-950/50 hover:border-slate-600'
                      }`}>
                      <span className="flex items-center gap-3">
                        <span className="h-3 w-3 rounded-full ring-2 ring-white/10" style={{ backgroundColor: option.color }}></span>
                        <span className="text-sm font-medium text-slate-200">{option.name}</span>
                      </span>
                      {theme === option.id && (
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4 text-amber-300">
                          <path d="m5 12 4 4L19 6"></path>
                        </svg>
                      )}
                    </button>
                  ))}
                </div>
                {themeStorageError && (
                  <p role="status" className="mt-3 text-xs text-amber-200">
                    Theme changed for this visit, but your browser couldn’t save it for next time.
                  </p>
                )}
              </div>
              )}
              <a
                href="logout.php"
                className="flex items-center gap-2 border-t border-slate-800 bg-slate-950/40 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800 hover:text-white">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4 text-amber-400">
                  <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                  <path d="m16 17 5-5-5-5"></path>
                  <path d="M21 12H9"></path>
                </svg>
                Log Out
              </a>
            </section>
          )}
        </div>
      </header>

      <main className="max-w-7xl mx-auto p-6 space-y-6">

        {portalData.appointmentNotice && (
          <div
            role={portalData.appointmentNoticeType === 'error' ? 'alert' : 'status'}
            className={`rounded-xl border p-4 text-sm ${
              portalData.appointmentNoticeType === 'error'
                ? 'border-red-500/30 bg-red-500/10 text-red-200'
                : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200'
            }`}
          >
            {portalData.appointmentNotice}
          </div>
        )}

        {/* Welcome Banner */}
        <div className="bg-gradient-to-r from-blue-900/40 via-slate-900/80 to-slate-900/90 border border-amber-500/30 rounded-2xl p-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 shadow-xl backdrop-blur-md">
          <div>
            <h2 className="text-2xl font-bold text-white tracking-wide">Welcome back, {clientProfile.fullname}</h2>
            <p className="text-slate-300 text-sm mt-1">Here is the latest update regarding your active cases, schedule, and shared legal documents.</p>
          </div>
          <button 
            onClick={() => setIsScheduleModalOpen(true)}
            className="bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 px-4 py-2 rounded-xl text-xs font-semibold text-amber-300 flex items-center gap-2 transition">
            <i data-lucide="calendar-plus" className="w-4 h-4 text-amber-400"></i> Set New Appointment
          </button>
        </div>

        {/* SECTION 1: Active Cases Table */}
        <section className="bg-slate-900/80 backdrop-blur-md border border-amber-500/20 rounded-2xl overflow-hidden shadow-xl">
          <div className="p-5 border-b border-slate-800 flex items-center gap-2">
            <i data-lucide="briefcase" className="w-5 h-5 text-amber-400"></i>
            <h3 className="font-bold text-white text-base tracking-wide">My Active Cases (<span className="text-amber-400 font-mono">cases</span>)</h3>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm text-slate-300">
              <thead className="bg-slate-950/60 text-amber-400/80 uppercase text-xs tracking-wider">
                <tr>
                  <th className="p-4">Case Number</th>
                  <th className="p-4">Title / Matter</th>
                  <th className="p-4">Status</th>
                  <th className="p-4">Filed Date</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/60">
                {activeCases.map((item) => (
                  <tr key={item.case_id} className="hover:bg-slate-800/40 transition">
                    <td className="p-4 font-mono text-amber-300 font-medium">{item.case_number}</td>
                    <td className="p-4 text-white font-semibold">{item.title}</td>
                    <td className="p-4">
                      <span className="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                        {item.status}
                      </span>
                    </td>
                    <td className="p-4 text-slate-400">{item.filed_date}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        {/* Grid Container for Appointments and Documents */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">

          {/* SECTION 2: Upcoming Appointments */}
          <section className="bg-slate-900/80 backdrop-blur-md border border-amber-500/20 rounded-2xl overflow-hidden shadow-xl">
            <div className="p-5 border-b border-slate-800 flex justify-between items-center">
              <div className="flex items-center gap-2">
                <i data-lucide="calendar" className="w-5 h-5 text-amber-400"></i>
                <h3 className="font-bold text-white text-base tracking-wide">Upcoming Appointments</h3>
              </div>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm text-slate-300">
                <thead className="bg-slate-950/60 text-amber-400/80 uppercase text-xs tracking-wider">
                  <tr>
                    <th className="p-4">Title / Type</th>
                    <th className="p-4">Date & Time</th>
                    <th className="p-4">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/60">
                  {upcomingAppointments.length === 0 ? (
                    <tr>
                      <td colSpan="3" className="p-6 text-center text-slate-400">No upcoming appointments.</td>
                    </tr>
                  ) : upcomingAppointments.map((app) => (
                    <tr key={app.appointment_id} className="hover:bg-slate-800/40 transition">
                      <td className="p-4">
                        <span className="font-semibold text-white block">{app.title}</span>
                        <span className="text-xs text-amber-400/80">{app.type}</span>
                      </td>
                      <td className="p-4 text-slate-300">
                        {new Date(app.appointment_date.replace(' ', 'T')).toLocaleString()}
                      </td>
                      <td className="p-4">
                        <span className="px-3 py-1 rounded-full text-xs font-medium bg-amber-500/10 text-amber-400 border border-amber-500/30">
                          {app.status}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>

          {/* SECTION 3: Shared Case Documents */}
          <section className="bg-slate-900/80 backdrop-blur-md border border-amber-500/20 rounded-2xl overflow-hidden shadow-xl">
            <div className="border-b border-slate-800 p-5">
              <div className="flex items-center gap-2">
                <i data-lucide="file-text" className="w-5 h-5 text-amber-400"></i>
                <h3 className="font-bold text-white text-base tracking-wide">My Shared Documents &amp; Files</h3>
              </div>
              <p className="mt-2 text-sm text-slate-400">Files related to your case. Request access to view confidential documents. Requests sync with the Staff portal in this browser only; documents are sample records.</p>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm text-slate-300">
                <thead className="bg-slate-950/60 text-amber-400/80 uppercase text-xs tracking-wider">
                  <tr>
                    <th className="p-4">Document Name</th>
                    <th className="p-4">Category</th>
                    <th className="p-4">Access Status</th>
                    <th className="p-4 text-right">Action</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/60">
                  {caseDocuments.map((doc) => {
                    const request = documentAccessRequests.find((item) => (
                      item.document_id === doc.document_id && item.client_email === clientProfile.email
                    ));
                    const accessStatus = request
                      ? request.status === 'Approved' ? 'Available'
                        : request.status === 'Pending' ? 'Requested'
                          : 'Locked'
                      : doc.access_status;
                    return (
                      <tr key={doc.document_id} className="hover:bg-slate-800/40 transition">
                        <td className="p-4 font-medium text-white flex items-center gap-2">
                          <i data-lucide="file-text" className="w-4 h-4 shrink-0 text-amber-400"></i>
                          <span>{doc.document_name}</span>
                        </td>
                        <td className="p-4 text-slate-300">{doc.category}</td>
                        <td className="p-4">
                          <span className={`rounded-full border px-3 py-1 text-xs font-medium ${
                            accessStatus === 'Available'
                              ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400'
                              : accessStatus === 'Requested'
                                ? 'border-amber-500/30 bg-amber-500/10 text-amber-300'
                                : 'border-slate-600 bg-slate-800 text-slate-300'
                          }`}>
                            {accessStatus}
                          </span>
                        </td>
                        <td className="p-4 text-right">
                          {accessStatus === 'Available' ? (
                            <button
                              type="button"
                              onClick={() => { setDocumentRequestNotice(''); setSelectedDocument(doc); }}
                              className="inline-flex items-center gap-1 rounded-lg bg-amber-500/10 px-3 py-1.5 text-xs font-semibold text-amber-300 shadow-sm transition hover:bg-amber-500 hover:text-slate-950"
                            >
                              <i data-lucide="eye" className="h-3.5 w-3.5"></i> View
                            </button>
                          ) : accessStatus === 'Requested' ? (
                            <button type="button" disabled className="inline-flex cursor-not-allowed items-center gap-1 rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-400">
                              <i data-lucide="clock-3" className="h-3.5 w-3.5"></i> Access Requested
                            </button>
                          ) : (
                            <button
                              type="button"
                              onClick={() => handleAccessRequest(doc.document_id)}
                              className="inline-flex items-center gap-1 rounded-lg bg-amber-500/10 px-3 py-1.5 text-xs font-semibold text-amber-300 shadow-sm transition hover:bg-amber-500 hover:text-slate-950"
                            >
                              Request View Access
                            </button>
                          )}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
            {documentRequestNotice && (
              <p role="status" className="border-t border-slate-800 px-5 py-3 text-xs text-amber-300">{documentRequestNotice}</p>
            )}
          </section>

        </div>
      </main>

      {selectedDocument && (
        <div
          className="fixed inset-0 z-[70] flex items-center justify-center overflow-y-auto bg-black/80 p-4 backdrop-blur-sm"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget) setSelectedDocument(null);
          }}
        >
          <section
            role="dialog"
            aria-modal="true"
            aria-labelledby="shared-document-title"
            className="my-auto w-full max-w-3xl overflow-hidden rounded-2xl border border-amber-500/30 bg-slate-900 shadow-2xl"
            onContextMenu={(event) => event.preventDefault()}
          >
            <div className="flex items-center justify-between border-b border-slate-800 px-6 py-4">
              <div className="flex min-w-0 items-center gap-3">
                <i data-lucide="file-text" className="h-5 w-5 shrink-0 text-amber-400"></i>
                <div className="min-w-0">
                  <h2 id="shared-document-title" className="truncate font-semibold text-white">{selectedDocument.document_name}</h2>
                  <p className="text-xs text-slate-400">{selectedDocument.category} · Uploaded {selectedDocument.upload_date}</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setSelectedDocument(null)}
                aria-label="Close document preview"
                className="ml-4 rounded-lg px-2 py-1 text-2xl leading-none text-slate-400 transition hover:bg-slate-800 hover:text-white"
              >
                &times;
              </button>
            </div>
            <div className="p-4 sm:p-6">
              <p className="mb-3 text-xs text-amber-300">Confidential document preview — watermark applied for this client session.</p>
              <div
                className="relative min-h-[24rem] select-none overflow-hidden rounded-lg bg-white p-8 text-slate-900 sm:p-12"
                onContextMenu={(event) => event.preventDefault()}
                onCopy={(event) => event.preventDefault()}
                onCut={(event) => event.preventDefault()}
                onDragStart={(event) => event.preventDefault()}
              >
                <div className="pointer-events-none absolute inset-0 grid select-none grid-cols-2 content-around justify-items-center gap-8 overflow-hidden p-6 text-center sm:grid-cols-3">
                  {Array.from({ length: 12 }, (_, index) => (
                    <span key={index} className="rotate-[-25deg] self-center whitespace-nowrap text-xs font-bold uppercase tracking-widest text-slate-800/20 sm:text-sm">
                      {clientProfile.fullname || 'Authorized Client'} · CONFIDENTIAL
                    </span>
                  ))}
                </div>
                <div className="relative z-10 space-y-5 font-serif">
                  <p className="border-b border-slate-300 pb-3 text-center text-xs font-semibold uppercase tracking-widest text-slate-500">Client Copy · {selectedDocument.category}</p>
                  <h3 className="text-center text-xl font-bold">{selectedDocument.preview[0]}</h3>
                  {selectedDocument.preview.slice(1).map((paragraph, index) => (
                    <p key={index} className="text-sm leading-7">{paragraph}</p>
                  ))}
                  <p className="border-t border-slate-300 pt-3 text-center text-[10px] uppercase tracking-wider text-slate-500">
                    Watermarked for {clientProfile.fullname || 'Authorized Client'} · {clientProfile.email || 'Client Portal'}
                  </p>
                </div>
              </div>
              <p className="mt-3 text-xs text-slate-400">The watermark and blocked Ctrl+P shortcut discourage copying, but cannot prevent screenshots or device-level capture. This is a sample preview, not the actual uploaded file.</p>
            </div>
          </section>
        </div>
      )}

      {/* --- 4. SCHEDULE APPOINTMENT MODAL (Based on Legal Appointment Form Template) --- */}
      {isScheduleModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 overflow-y-auto">
          <div className="bg-slate-900 border border-amber-500/30 rounded-2xl max-w-3xl w-full p-7 shadow-2xl my-8 relative">

            {/* Close Modal Button */}
            <button 
              onClick={() => setIsScheduleModalOpen(false)}
              className="absolute top-4 right-4 text-slate-400 hover:text-white transition">
              <i data-lucide="x" className="w-6 h-6"></i>
            </button>

            {/* Header with Title & Subtitle */}
            <div className="flex items-center justify-between border-b border-slate-800 pb-4 mb-6">
              <div>
                <h2 className="text-2xl font-bold text-white tracking-wide">Legal Appointment Form</h2>
                <p className="text-xs text-amber-400 font-medium italic mt-0.5">Schedule Your Legal Consultation</p>
              </div>
              <div className="text-right hidden sm:block">
                <span className="text-sm font-bold tracking-wider text-slate-300 uppercase block"></span>
              </div>
            </div>

            <form method="post" action="index.php" className="space-y-6 text-sm text-slate-200">
              <input type="hidden" name="_csrf" value={portalData.csrfToken} />
              <input
                type="hidden"
                name="matter_type"
                value={appointmentFormData.legal_matter_type}
              />

              <section>
                <h3 className="text-amber-400 font-semibold text-base mb-3 border-b border-slate-800 pb-1">Client Information</h3>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div className="sm:col-span-2">
                    <label htmlFor="client-fullname" className="block text-xs text-slate-400 mb-1">Full Name:</label>
                    <input
                      id="client-fullname"
                      type="text"
                      value={clientProfile.fullname || ''}
                      readOnly
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white"
                    />
                  </div>
                  <div className="sm:col-span-2">
                    <label htmlFor="client-address" className="block text-xs text-slate-400 mb-1">Full Address:</label>
                    <input
                      id="client-address"
                      type="text"
                      value={clientProfile.address || ''}
                      readOnly
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white"
                    />
                  </div>
                  <div>
                    <label htmlFor="client-phone" className="block text-xs text-slate-400 mb-1">Phone Number:</label>
                    <input
                      id="client-phone"
                      type="text"
                      value={clientProfile.phone || ''}
                      readOnly
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white"
                    />
                  </div>
                  <div>
                    <label htmlFor="client-email" className="block text-xs text-slate-400 mb-1">Email Address:</label>
                    <input
                      id="client-email"
                      type="email"
                      value={clientProfile.email || ''}
                      readOnly
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white"
                    />
                  </div>
                </div>
              </section>

              <div>
                <h3 className="text-amber-400 font-semibold text-base mb-3 border-b border-slate-800 pb-1">Appointment Details</h3>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-3">
                  <div>
                    <label htmlFor="preferred-attorney" className="block text-xs text-slate-400 mb-1">Preferred Attorney (if any):</label>
                    <input
                      id="preferred-attorney"
                      name="preferred_attorney"
                      type="text"
                      maxLength={150}
                      placeholder="e.g. Atty. Smith"
                      value={appointmentFormData.preferred_attorney}
                      onChange={(e) => setAppointmentFormData({...appointmentFormData, preferred_attorney: e.target.value})}
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-amber-500"
                    />

                    <label className="block text-xs text-slate-400 mt-4 mb-2">Type of Legal Matter (Check one):</label>
                    <div className="space-y-1.5 text-xs text-slate-300">
                      {['Family Law', 'Criminal Law', 'Real Estate Law', 'Business Law', 'Other'].map((matter) => (
                        <label key={matter} className="flex items-center gap-2 cursor-pointer">
                          <input 
                            type="radio" 
                            name="legal_matter" 
                            value={matter}
                            checked={appointmentFormData.legal_matter_type === matter}
                            onChange={(e) => setAppointmentFormData({...appointmentFormData, legal_matter_type: e.target.value})}
                            className="accent-amber-500"
                          />
                          {matter}
                        </label>
                      ))}
                      {appointmentFormData.legal_matter_type === 'Other' && (
                        <input 
                          type="text" 
                          name="other_matter"
                          required
                          maxLength="50"
                          placeholder="Specify other matter..."
                          value={appointmentFormData.other_matter}
                          onChange={(e) => setAppointmentFormData({...appointmentFormData, other_matter: e.target.value})}
                          className="w-full bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1 text-xs text-white mt-1 focus:outline-none focus:border-amber-500"
                        />
                      )}
                    </div>
                  </div>

                  <div>
                    <label htmlFor="issue-description" className="block text-xs text-slate-400 mb-1">Brief Description of Legal Issue:</label>
                    <textarea
                      id="issue-description"
                      name="issue_description"
                      rows={5}
                      maxLength={2000}
                      placeholder="Please describe your legal situation..."
                      value={appointmentFormData.issue_description}
                      onChange={(e) => setAppointmentFormData({...appointmentFormData, issue_description: e.target.value})}
                      className="w-full resize-y bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white placeholder:text-slate-400 focus:outline-none focus:border-amber-500"
                    />
                    <label htmlFor="appointment_date" className="block text-xs text-slate-400 mt-3 mb-1">Preferred Date &amp; Time:</label>
                    <input
                      id="appointment_date"
                      name="appointment_date"
                      type="datetime-local"
                      required
                      min={new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16)}
                      value={appointmentFormData.appointment_date}
                      onChange={(e) => setAppointmentFormData({...appointmentFormData, appointment_date: e.target.value})}
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-amber-500"
                    />
                    <p className="mt-1 text-xs text-slate-400">Staff will review and confirm your requested appointment time.</p>
                  </div>
                </div>
              </div>

              {/* Confirmation */}
              <div className="flex items-start gap-2 pt-2 border-t border-slate-800">
                <input 
                  type="checkbox" 
                  required
                  id="confirm-terms"
                  name="confirmed"
                  value="1"
                  checked={appointmentFormData.confirmed}
                  onChange={(e) => setAppointmentFormData({...appointmentFormData, confirmed: e.target.checked})}
                  className="mt-1 accent-amber-500"
                />
                <label htmlFor="confirm-terms" className="text-xs text-slate-400 leading-snug cursor-pointer">
                  I confirm the accuracy of the provided information and agree to the appointment terms and conditions.
                </label>
              </div>

              {/* Form Action Buttons */}
              <div className="flex justify-end gap-3 pt-2">
                <button 
                  type="button" 
                  onClick={() => setIsScheduleModalOpen(false)}
                  className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-xs font-semibold transition">
                  Cancel
                </button>
                <button 
                  type="submit" 
                  className="px-5 py-2 bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-500 hover:to-amber-400 text-slate-950 font-bold rounded-lg text-xs transition">
                  Submit Appointment Request
                </button>
              </div>

            </form>
          </div>
        </div>
      )}

    </div>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<ClientPortal />);
