interface TabNavigationProps {
  activeTab: string;
  onTabChange: (tab: string) => void;
}

const tabs = [
  { id: "devices", label: "장치 제어", icon: "🎛️" },
  { id: "mist", label: "분무수경", icon: "💧" },
  { id: "environment", label: "환경 모니터링", icon: "📊" },
  { id: "cameras", label: "카메라", icon: "📷" },
  { id: "settings", label: "설정", icon: "⚙️" },
];

export default function TabNavigation({ activeTab, onTabChange }: TabNavigationProps) {
  return (
    <nav className="bg-white shadow-sm border-b border-gray-200 flex-shrink-0">
      <div className="max-w-screen-2xl mx-auto px-4">
        <div className="flex justify-center gap-1 overflow-x-auto">
          {tabs.map((tab) => (
            <button
              key={tab.id}
              onClick={() => onTabChange(tab.id)}
              className={`
                flex items-center gap-1.5 px-4 py-3 text-sm font-medium
                transition-all duration-200 whitespace-nowrap border-b-2
                ${activeTab === tab.id
                  ? 'border-farm-500 bg-farm-50 text-farm-700'
                  : 'border-transparent text-gray-500 hover:bg-gray-50 hover:text-farm-700'
                }
              `}
            >
              <span className="text-lg">{tab.icon}</span>
              <span>{tab.label}</span>
            </button>
          ))}
        </div>
      </div>
    </nav>
  );
}
