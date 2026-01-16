interface TabNavigationProps {
  activeTab: string;
  onTabChange: (tab: string) => void;
}

const tabs = [
  { id: "devices", label: "장치", icon: "🎛️" },
  { id: "mist", label: "분무", icon: "💧" },
  { id: "environment", label: "환경", icon: "📊" },
  { id: "cameras", label: "카메라", icon: "📷" },
  { id: "settings", label: "설정", icon: "⚙️" },
];

export default function TabNavigation({ activeTab, onTabChange }: TabNavigationProps) {
  return (
    <nav className="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 shadow-lg z-50 safe-area-bottom">
      <div className="grid grid-cols-5 h-16">
        {tabs.map((tab) => (
          <button
            key={tab.id}
            onClick={() => onTabChange(tab.id)}
            className={`
              flex flex-col items-center justify-center gap-0.5
              transition-all duration-200 relative
              ${activeTab === tab.id
                ? 'text-farm-600'
                : 'text-gray-400 active:text-farm-500'
              }
            `}
          >
            {/* 활성 탭 인디케이터 */}
            {activeTab === tab.id && (
              <div className="absolute top-0 left-1/2 -translate-x-1/2 w-12 h-1 bg-farm-500 rounded-b-full" />
            )}
            <span className="text-2xl">{tab.icon}</span>
            <span className="text-[10px] font-medium">{tab.label}</span>
          </button>
        ))}
      </div>
    </nav>
  );
}
