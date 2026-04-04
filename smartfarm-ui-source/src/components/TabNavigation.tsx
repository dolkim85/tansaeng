interface TabNavigationProps {
  activeTab: string;
  onTabChange: (tab: string) => void;
}

const tabs = [
  { id: "dashboard", label: "대시보드", icon: "🏭" },
  { id: "devices", label: "장치 제어", icon: "🎛️" },
  { id: "mist", label: "분무수경", icon: "💧" },
  { id: "environment", label: "환경 모니터링", icon: "📊" },
  { id: "cameras", label: "카메라", icon: "📷" },
  { id: "mistlogs", label: "분무 로그", icon: "📋" },
  { id: "settings", label: "설정", icon: "⚙️" },
];

export default function TabNavigation({ activeTab, onTabChange }: TabNavigationProps) {
  return (
    <nav className="bg-white shadow-sm border-b border-gray-200 flex-shrink-0">
      <div className="max-w-screen-2xl mx-auto px-2 sm:px-4">
        <div className="flex justify-start sm:justify-center gap-1 overflow-x-auto scrollbar-hide">
          {tabs.map((tab) => (
            <button
              key={tab.id}
              onClick={() => onTabChange(tab.id)}
              className={`
                flex items-center gap-1.5 px-3 sm:px-4 py-3 text-xs sm:text-sm font-medium
                transition-all duration-200 whitespace-nowrap border-b-2 flex-shrink-0
                ${activeTab === tab.id
                  ? 'border-farm-500 bg-farm-50 text-farm-700'
                  : 'border-transparent text-gray-500 hover:bg-gray-50 hover:text-farm-700'
                }
              `}
            >
              <span className="text-base sm:text-lg">{tab.icon}</span>
              <span className="hidden xs:inline sm:inline">{tab.label}</span>
            </button>
          ))}
        </div>
      </div>
    </nav>
  );
}
