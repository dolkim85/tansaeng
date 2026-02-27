import { Component, type ReactNode } from "react";

interface Props {
  children: ReactNode;
}

interface State {
  hasError: boolean;
  errorMessage: string;
}

export default class ErrorBoundary extends Component<Props, State> {
  state: State = { hasError: false, errorMessage: "" };

  static getDerivedStateFromError(error: Error): State {
    return { hasError: true, errorMessage: error.message };
  }

  componentDidCatch(error: Error, info: { componentStack: string }) {
    console.error("[ErrorBoundary]", error, info.componentStack);
  }

  handleRetry = () => {
    this.setState({ hasError: false, errorMessage: "" });
  };

  render() {
    if (this.state.hasError) {
      return (
        <div className="flex flex-col items-center justify-center h-full min-h-64 p-8 text-center">
          <div className="bg-white border-2 border-red-200 rounded-xl p-6 max-w-md w-full shadow-sm">
            <div className="text-4xl mb-3">⚠️</div>
            <h2 className="text-base font-semibold text-gray-800 mb-2">
              이 탭에서 오류가 발생했습니다
            </h2>
            {this.state.errorMessage && (
              <p className="text-xs text-gray-500 bg-gray-50 rounded px-3 py-2 mb-4 font-mono break-all">
                {this.state.errorMessage}
              </p>
            )}
            <button
              onClick={this.handleRetry}
              className="bg-farm-500 hover:bg-farm-600 text-gray-900 font-medium px-5 py-2 rounded-lg transition-colors text-sm"
            >
              다시 시도
            </button>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}
