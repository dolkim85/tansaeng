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
    <nav style={{
      background: "white",
      boxShadow: "0 1px 3px 0 rgb(0 0 0 / 0.1)",
      borderBottom: "1px solid #e5e7eb",
      flexShrink: 0
    }}>
      <div style={{
        maxWidth: "1400px",
        margin: "0 auto",
        padding: "0 16px"
      }}>
        <div style={{
          display: "flex",
          justifyContent: "center",
          gap: "4px",
          overflowX: "auto"
        }}>
          {tabs.map((tab) => (
            <button
              key={tab.id}
              onClick={() => onTabChange(tab.id)}
              style={{
                display: "flex",
                alignItems: "center",
                gap: "6px",
                padding: "12px 16px",
                fontSize: "0.875rem",
                fontWeight: "500",
                transition: "all 0.2s",
                whiteSpace: "nowrap",
                border: "none",
                borderBottom: activeTab === tab.id ? "3px solid #10b981" : "3px solid transparent",
                background: activeTab === tab.id ? "#d1fae5" : "transparent",
                color: activeTab === tab.id ? "#047857" : "#6b7280",
                cursor: "pointer"
              }}
              onMouseEnter={(e) => {
                if (activeTab !== tab.id) {
                  e.currentTarget.style.background = "#f3f4f6";
                  e.currentTarget.style.color = "#047857";
                }
              }}
              onMouseLeave={(e) => {
                if (activeTab !== tab.id) {
                  e.currentTarget.style.background = "transparent";
                  e.currentTarget.style.color = "#6b7280";
                }
              }}
            >
              <span style={{ fontSize: "1.125rem" }}>{tab.icon}</span>
              <span>{tab.label}</span>
            </button>
          ))}
        </div>
      </div>
    </nav>
  );
}
