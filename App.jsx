import { useState, useEffect } from 'react'
import './App.css'

function App() {
  const [display, setDisplay] = useState('0')
  const [previousValue, setPreviousValue] = useState(null)
  const [operation, setOperation] = useState(null)
  const [waitingForOperand, setWaitingForOperand] = useState(false)
  const [history, setHistory] = useState([])
  const [scientificMode, setScientificMode] = useState(false)
  const [angleMode, setAngleMode] = useState('DEG')

  useEffect(() => {
    const handleKeyPress = (e) => {
      if (/[0-9]/.test(e.key)) {
        handleNumberClick(parseInt(e.key))
      } else if (e.key === '.') {
        handleDecimal()
      } else if (e.key === '+' || e.key === '-') {
        handleOperation(e.key === '+' ? '+' : '-')
      } else if (e.key === '*') {
        handleOperation('×')
        e.preventDefault()
      } else if (e.key === '/') {
        handleOperation('÷')
        e.preventDefault()
      } else if (e.key === '%') {
        handleOperation('%')
      } else if (e.key === 'Enter' || e.key === '=') {
        handleEquals()
        e.preventDefault()
      } else if (e.key === 'Backspace') {
        handleBackspace()
        e.preventDefault()
      } else if (e.key === 'Escape') {
        handleClear()
      }
    }

    window.addEventListener('keydown', handleKeyPress)
    return () => window.removeEventListener('keydown', handleKeyPress)
  }, [display, previousValue, operation, waitingForOperand])

  const handleNumberClick = (num) => {
    if (waitingForOperand) {
      setDisplay(String(num))
      setWaitingForOperand(false)
    } else {
      setDisplay(display === '0' ? String(num) : display + num)
    }
  }

  const handleDecimal = () => {
    if (waitingForOperand) {
      setDisplay('0.')
      setWaitingForOperand(false)
    } else if (display.indexOf('.') === -1) {
      setDisplay(display + '.')
    }
  }

  const handleOperation = (nextOperation) => {
    const inputValue = parseFloat(display)

    if (previousValue === null) {
      setPreviousValue(inputValue)
    } else if (operation) {
      const result = performCalculation(previousValue, inputValue, operation)
      setDisplay(String(result))
      setPreviousValue(result)
    }

    setWaitingForOperand(true)
    setOperation(nextOperation)
  }

  const performCalculation = (prev, current, op) => {
    let result
    switch (op) {
      case '+':
        result = prev + current
        break
      case '-':
        result = prev - current
        break
      case '×':
        result = prev * current
        break
      case '÷':
        result = prev / current
        break
      case '%':
        result = prev % current
        break
      case '^':
        result = Math.pow(prev, current)
        break
      default:
        result = current
    }
    return result
  }

  const handleEquals = () => {
    const inputValue = parseFloat(display)

    if (previousValue !== null && operation) {
      const result = performCalculation(previousValue, inputValue, operation)
      const calculation = `${previousValue} ${operation} ${inputValue} = ${result}`
      setHistory([...history, calculation])
      setDisplay(String(result))
      setPreviousValue(null)
      setOperation(null)
      setWaitingForOperand(true)
    }
  }

  const handleScientificFunction = (func) => {
    let result
    const value = parseFloat(display)
    const radians = angleMode === 'DEG' ? (value * Math.PI) / 180 : value

    switch (func) {
      case 'sin':
        result = Math.sin(radians)
        break
      case 'cos':
        result = Math.cos(radians)
        break
      case 'tan':
        result = Math.tan(radians)
        break
      case 'sqrt':
        result = Math.sqrt(value)
        break
      case 'cbrt':
        result = Math.cbrt(value)
        break
      case 'log':
        result = Math.log10(value)
        break
      case 'ln':
        result = Math.log(value)
        break
      case 'exp':
        result = Math.exp(value)
        break
      case 'factorial':
        result = factorial(value)
        break
      case 'reciprocal':
        result = 1 / value
        break
      case 'pi':
        setDisplay(String(Math.PI))
        return
      case 'e':
        setDisplay(String(Math.E))
        return
      default:
        result = value
    }

    setDisplay(String(result))
    setWaitingForOperand(true)
  }

  const factorial = (n) => {
    if (n < 0) return NaN
    if (n === 0 || n === 1) return 1
    let result = 1
    for (let i = 2; i <= n; i++) result *= i
    return result
  }

  const handleClear = () => {
    setDisplay('0')
    setPreviousValue(null)
    setOperation(null)
    setWaitingForOperand(false)
  }

  const handleBackspace = () => {
    if (display.length > 1) {
      setDisplay(display.slice(0, -1))
    } else {
      setDisplay('0')
    }
  }

  const handleNegate = () => {
    setDisplay(String(parseFloat(display) * -1))
  }

  const clearHistory = () => {
    setHistory([])
  }

  return (
    <div className="calculator-app">
      <div className="main-container">
        <div className={`calculator ${scientificMode ? 'scientific' : 'basic'}`}>
          <div className="header">
            <h1>Scientific Calculator</h1>
            <div className="controls">
              <button className="mode-toggle" onClick={() => setScientificMode(!scientificMode)}>
                {scientificMode ? 'Basic' : 'Scientific'}
              </button>
              {scientificMode && (
                <button
                  className={`angle-toggle ${angleMode === 'DEG' ? 'deg-active' : 'rad-active'}`}
                  onClick={() => setAngleMode(angleMode === 'DEG' ? 'RAD' : 'DEG')}
                >
                  {angleMode}
                </button>
              )}
            </div>
          </div>

          <div className="display">{display}</div>

          <div className="buttons-container">
            <div className="buttons-grid basic-grid">
              <button className="btn btn-function" onClick={handleClear}>AC</button>
              <button className="btn btn-function" onClick={handleBackspace}>⌫</button>
              <button className="btn btn-function" onClick={handleNegate}>±</button>
              <button className="btn btn-operator" onClick={() => handleOperation('÷')}>÷</button>

              <button className="btn btn-number" onClick={() => handleNumberClick(7)}>7</button>
              <button className="btn btn-number" onClick={() => handleNumberClick(8)}>8</button>
              <button className="btn btn-number" onClick={() => handleNumberClick(9)}>9</button>
              <button className="btn btn-operator" onClick={() => handleOperation('×')}>×</button>

              <button className="btn btn-number" onClick={() => handleNumberClick(4)}>4</button>
              <button className="btn btn-number" onClick={() => handleNumberClick(5)}>5</button>
              <button className="btn btn-number" onClick={() => handleNumberClick(6)}>6</button>
              <button className="btn btn-operator" onClick={() => handleOperation('-')}>−</button>

              <button className="btn btn-number" onClick={() => handleNumberClick(1)}>1</button>
              <button className="btn btn-number" onClick={() => handleNumberClick(2)}>2</button>
              <button className="btn btn-number" onClick={() => handleNumberClick(3)}>3</button>
              <button className="btn btn-operator" onClick={() => handleOperation('+')}>+</button>

              <button className="btn btn-number btn-zero" onClick={() => handleNumberClick(0)}>0</button>
              <button className="btn btn-number" onClick={handleDecimal}>.</button>
              <button className="btn btn-operator" onClick={() => handleOperation('%')}>%</button>
              <button className="btn btn-equals" onClick={handleEquals}>=</button>
            </div>

            {scientificMode && (
              <div className="buttons-grid scientific-grid">
                <button className="btn btn-science" onClick={() => handleScientificFunction('sin')}>sin</button>
                <button className="btn btn-science" onClick={() => handleScientificFunction('cos')}>cos</button>
                <button className="btn btn-science" onClick={() => handleScientificFunction('tan')}>tan</button>
                <button className="btn btn-operator" onClick={() => handleOperation('^')}>x^y</button>

                <button className="btn btn-science" onClick={() => handleScientificFunction('sqrt')}>√x</button>
                <button className="btn btn-science" onClick={() => handleScientificFunction('cbrt')}>∛x</button>
                <button className="btn btn-science" onClick={() => handleScientificFunction('log')}>log</button>
                <button className="btn btn-science" onClick={() => handleScientificFunction('ln')}>ln</button>

                <button className="btn btn-science" onClick={() => handleScientificFunction('exp')}>e^x</button>
                <button className="btn btn-science" onClick={() => handleScientificFunction('factorial')}>x!</button>
                <button className="btn btn-science" onClick={() => handleScientificFunction('reciprocal')}>1/x</button>
                <button className="btn btn-science" onClick={() => handleScientificFunction('pi')}>π</button>

                <button className="btn btn-science" onClick={() => handleScientificFunction('e')}>e</button>
              </div>
            )}
          </div>
        </div>

        {history.length > 0 && (
          <div className="history">
            <div className="history-header">
              <h2>History</h2>
              <button className="btn btn-clear-history" onClick={clearHistory}>Clear</button>
            </div>
            <div className="history-list">
              {history.map((calc, idx) => (
                <div key={idx} className="history-item">{calc}</div>
              ))}
            </div>
          </div>
        )}
      </div>

      <div className="keyboard-info">
        💡 Keyboard support: 0-9, +, -, *, /, ., Enter (=), Backspace, Escape (AC)
      </div>
    </div>
  )
}

export default App
